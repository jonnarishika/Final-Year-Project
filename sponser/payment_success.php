<?php
require_once(__DIR__ . '/../razorpay-php/Razorpay.php');
use Razorpay\Api\Api;
session_start();
require_once __DIR__ . '/../db_config.php';

// Check if sponsor is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../signup_and_login/login_template.php");
    exit();
}

// Validate required parameters
if (!isset($_GET['payment_id']) || !isset($_GET['order_id']) || !isset($_GET['signature'])) {
    header("Location: payment_failed.php?error=missing_params");
    exit();
}

$payment_id = $_GET['payment_id'];
$order_id = $_GET['order_id'];
$signature = $_GET['signature'];
$sponsor_id = $_SESSION['user_id'];

try {
    // Razorpay API credentials
    $api_key = 'rzp_test_RiQdqG3QtpFjdt';
    $api_secret = 'mMhzmW57NeJ7q3AWTUX37wKx';
    $api = new Api($api_key, $api_secret);
    
    // STEP 1: Verify Razorpay Signature (Critical Security Check)
    $generated_signature = hash_hmac('sha256', $order_id . "|" . $payment_id, $api_secret);
    
    if ($generated_signature !== $signature) {
        throw new Exception("Invalid payment signature");
    }
    
    // STEP 2: Fetch payment details from Razorpay
    $payment = $api->payment->fetch($payment_id);
    
    if ($payment['status'] !== 'captured' && $payment['status'] !== 'authorized') {
        throw new Exception("Payment not successful");
    }
    
    // STEP 3: Find the donation record using order_id
    $stmt = $conn->prepare("
        SELECT donation_id, sponsor_id, child_id, amount 
        FROM donations 
        WHERE razorpay_order_id = ? AND status = 'Pending'
    ");
    $stmt->bind_param("s", $order_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $donation = $result->fetch_assoc();
    
    if (!$donation) {
        throw new Exception("Donation record not found");
    }
    
    // Verify sponsor_id matches session
    if ($donation['sponsor_id'] != $sponsor_id) {
        throw new Exception("Unauthorized access");
    }
    
    // STEP 4: Generate Final Donation ID (DON-2025-0001 format)
    $year = date('Y');
    
    // Get the last donation number for this year
    $stmt = $conn->prepare("
        SELECT receipt_no 
        FROM donations 
        WHERE receipt_no LIKE ? 
        ORDER BY donation_id DESC 
        LIMIT 1
    ");
    $pattern = "DON-{$year}-%";
    $stmt->bind_param("s", $pattern);
    $stmt->execute();
    $result = $stmt->get_result();
    $last_donation = $result->fetch_assoc();
    
    if ($last_donation && preg_match('/DON-\d{4}-(\d{4})/', $last_donation['receipt_no'], $matches)) {
        $next_number = intval($matches[1]) + 1;
    } else {
        $next_number = 1;
    }
    
    $final_donation_id = sprintf("DON-%s-%04d", $year, $next_number);
    
    // STEP 5: Update donations table
    $payment_date = date('Y-m-d H:i:s');
    $payment_method = 'UPI'; // Default, you can parse from Razorpay response if needed
    
    $stmt = $conn->prepare("
        UPDATE donations 
        SET 
            receipt_no = ?,
            razorpay_payment_id = ?,
            razorpay_signature = ?,
            status = 'Success',
            payment_date = ?,
            payment_method = ?
        WHERE donation_id = ?
    ");
    
    $stmt->bind_param("sssssi", 
        $final_donation_id,
        $payment_id,
        $signature,
        $payment_date,
        $payment_method,
        $donation['donation_id']
    );
    
    if (!$stmt->execute()) {
        throw new Exception("Failed to update donation record");
    }
    
    // STEP 6: Check if sponsorship record already exists
    $stmt = $conn->prepare("
        SELECT sponsorship_id 
        FROM sponsorships 
        WHERE sponsor_id = ? AND child_id = ? AND end_date IS NULL
    ");
    $stmt->bind_param("ii", $sponsor_id, $donation['child_id']);
    $stmt->execute();
    $existing_sponsorship = $stmt->get_result()->fetch_assoc();
    
    // STEP 7: Create new sponsorship record only if none exists
    if (!$existing_sponsorship) {
        $stmt = $conn->prepare("
            INSERT INTO sponsorships 
            (sponsor_id, child_id, start_date) 
            VALUES (?, ?, NOW())
        ");
        $stmt->bind_param("ii", $sponsor_id, $donation['child_id']);
        
        if (!$stmt->execute()) {
            throw new Exception("Failed to create sponsorship record");
        }
        
        // STEP 8: Update child's status and current sponsor
        $stmt = $conn->prepare("
            UPDATE children 
            SET status = 'Sponsored', sponsor_id = ? 
            WHERE child_id = ?
        ");
        $stmt->bind_param("ii", $sponsor_id, $donation['child_id']);
        $stmt->execute();
    }
    
    // STEP 9: Success! Redirect to thank you page
    header("Location: thank_you.php?donation_id=" . urlencode($final_donation_id));
    exit();
    
} catch (Exception $e) {
    // Log error (in production, use proper logging)
    error_log("Payment verification failed: " . $e->getMessage());
    
    // Update donation status to failed if we have the donation_id
    if (isset($donation['donation_id'])) {
        $stmt = $conn->prepare("
            UPDATE donations 
            SET status = 'Failed' 
            WHERE donation_id = ?
        ");
        $stmt->bind_param("i", $donation['donation_id']);
        $stmt->execute();
    }
    
    // Redirect to failure page
    header("Location: payment_failed.php?error=" . urlencode($e->getMessage()));
    exit();
}
?>