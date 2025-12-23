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

// Check if payment parameters exist
if (!isset($_GET['payment_id']) || !isset($_GET['order_id']) || !isset($_GET['signature'])) {
    header("Location: children_list.php");
    exit();
}

$payment_id = $_GET['payment_id'];
$order_id = $_GET['order_id'];
$signature = $_GET['signature'];
$child_id = $_GET['child_id'] ?? null;
$user_id = $_SESSION['user_id'];

// FIXED: Get actual sponsor_id from sponsors table
$stmt = $conn->prepare("SELECT sponsor_id FROM sponsors WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$sponsor_result = $stmt->get_result();
$sponsor_data = $sponsor_result->fetch_assoc();

if (!$sponsor_data) {
    header("Location: payment_failed.php?error=Sponsor not found");
    exit();
}

$sponsor_id = $sponsor_data['sponsor_id'];

// Verify signature with Razorpay
$api_key = 'rzp_test_RiQdqG3QtpFjdt';
$api_secret = 'mMhzmW57NeJ7q3AWTUX37wKx';

$generated_signature = hash_hmac('sha256', $order_id . "|" . $payment_id, $api_secret);

if ($generated_signature !== $signature) {
    header("Location: payment_failed.php?error=Invalid payment signature&child_id=" . urlencode($child_id));
    exit();
}

try {
    $api = new Api($api_key, $api_secret);
    
    // Fetch payment details from Razorpay to verify
    $payment = $api->payment->fetch($payment_id);
    
    if ($payment['status'] !== 'captured') {
        header("Location: payment_failed.php?error=Payment not captured&child_id=" . urlencode($child_id));
        exit();
    }
    
    // Find the pending donation and update it
    $stmt = $conn->prepare("
        SELECT donation_id, amount, child_id
        FROM donations 
        WHERE razorpay_order_id = ? 
        AND sponsor_id = ? 
        AND status = 'Pending'
        LIMIT 1
    ");
    
    $stmt->bind_param("si", $order_id, $sponsor_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $donation = $result->fetch_assoc();
    
    if (!$donation) {
        header("Location: payment_failed.php?error=Donation record not found&child_id=" . urlencode($child_id));
        exit();
    }
    
    $donation_id = $donation['donation_id'];
    $donation_child_id = $donation['child_id'];
    
    // Generate receipt number
    $receipt_no = 'RCP-' . date('YmdHis') . '-' . $donation_id;
    
    // Update donation status to Success
    $stmt = $conn->prepare("
        UPDATE donations 
        SET status = 'Success', 
            razorpay_payment_id = ?, 
            razorpay_signature = ?,
            receipt_no = ?,
            payment_date = NOW()
        WHERE donation_id = ?
    ");
    
    $stmt->bind_param("sssi", $payment_id, $signature, $receipt_no, $donation_id);
    $stmt->execute();
    
    // Check if sponsorship already exists
    $stmt = $conn->prepare("
        SELECT sponsorship_id 
        FROM sponsorships 
        WHERE sponsor_id = ? AND child_id = ? AND end_date IS NULL
    ");
    $stmt->bind_param("ii", $sponsor_id, $donation_child_id);
    $stmt->execute();
    $existing_sponsorship = $stmt->get_result()->fetch_assoc();
    
    // Create new sponsorship record only if none exists
    if (!$existing_sponsorship) {
        $stmt = $conn->prepare("
            INSERT INTO sponsorships 
            (sponsor_id, child_id, start_date) 
            VALUES (?, ?, NOW())
        ");
        $stmt->bind_param("ii", $sponsor_id, $donation_child_id);
        $stmt->execute();
        
        // Update child's status and current sponsor
        $stmt = $conn->prepare("
            UPDATE children 
            SET status = 'Sponsored', sponsor_id = ? 
            WHERE child_id = ?
        ");
        $stmt->bind_param("ii", $sponsor_id, $donation_child_id);
        $stmt->execute();
    }
    
    // Fetch complete donation details for display
    $stmt = $conn->prepare("
        SELECT 
            d.donation_id,
            d.receipt_no,
            d.amount,
            d.payment_date,
            d.razorpay_payment_id,
            c.first_name AS child_first_name,
            c.last_name AS child_last_name,
            c.profile_picture AS child_photo,
            s.first_name AS sponsor_first_name,
            s.last_name AS sponsor_last_name,
            u.email AS sponsor_email
        FROM donations d
        JOIN children c ON d.child_id = c.child_id
        JOIN sponsors s ON d.sponsor_id = s.sponsor_id
        JOIN users u ON s.user_id = u.user_id
        WHERE d.donation_id = ?
    ");
    
    $stmt->bind_param("i", $donation_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $donation_data = $result->fetch_assoc();
    
    if (!$donation_data) {
        header("Location: payment_failed.php?error=" . urlencode("Could not retrieve donation details") . "&child_id=" . urlencode($child_id));
        exit();
    }
    
} catch (Exception $e) {
    header("Location: payment_failed.php?error=" . urlencode($e->getMessage()) . "&child_id=" . urlencode($child_id));
    exit();
}

$donation = $donation_data;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Successful - Thank You!</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
            min-height: 100vh;
            padding: 40px 20px;
            position: relative;
            overflow-x: hidden;
        }

        body::before {
            content: '';
            position: fixed;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(56, 239, 125, 0.2) 0%, transparent 70%);
            animation: pulse 4s ease-in-out infinite;
            z-index: 0;
        }

        @keyframes pulse {
            0%, 100% { transform: scale(1); opacity: 0.5; }
            50% { transform: scale(1.1); opacity: 0.8; }
        }

        .confetti {
            position: fixed;
            width: 10px;
            height: 10px;
            background: rgba(255, 255, 255, 0.8);
            animation: fall 3s linear infinite;
            z-index: 1;
        }

        @keyframes fall {
            to { transform: translateY(100vh) rotate(360deg); }
        }

        .container {
            max-width: 700px;
            width: 100%;
            margin: 0 auto;
            position: relative;
            z-index: 2;
        }

        .success-card {
            background: rgba(255, 255, 255, 0.25);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 2px solid rgba(255, 255, 255, 0.4);
            border-radius: 30px;
            padding: 50px 40px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.15);
            text-align: center;
            animation: slideUp 0.6s ease-out;
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .success-icon {
            width: 100px;
            height: 100px;
            background: rgba(255, 255, 255, 0.9);
            border-radius: 50%;
            margin: 0 auto 30px;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 10px 40px rgba(56, 239, 125, 0.4);
            animation: scaleIn 0.5s ease-out 0.2s both;
        }

        @keyframes scaleIn {
            from { transform: scale(0); }
            to { transform: scale(1); }
        }

        .success-icon svg {
            width: 60px;
            height: 60px;
            stroke: #11998e;
            stroke-width: 3;
            stroke-linecap: round;
            stroke-linejoin: round;
            fill: none;
            animation: checkmark 0.6s ease-out 0.4s both;
        }

        @keyframes checkmark {
            0% { stroke-dasharray: 0, 100; }
            100% { stroke-dasharray: 100, 100; }
        }

        h1 {
            font-size: 36px;
            color: #ffffff;
            margin-bottom: 15px;
            font-weight: 700;
            text-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        .subtitle {
            font-size: 18px;
            color: rgba(255, 255, 255, 0.95);
            margin-bottom: 40px;
            line-height: 1.6;
        }

        .child-info {
            background: rgba(255, 255, 255, 0.2);
            border-radius: 20px;
            padding: 25px;
            margin-bottom: 30px;
            display: flex;
            align-items: center;
            gap: 20px;
            border: 1px solid rgba(255, 255, 255, 0.3);
        }

        .child-photo {
            width: 80px;
            height: 80px;
            border-radius: 15px;
            object-fit: cover;
            border: 3px solid rgba(255, 255, 255, 0.5);
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            flex-shrink: 0;
        }

        .child-details {
            flex: 1;
            text-align: left;
        }

        .child-details h3 {
            font-size: 22px;
            color: #ffffff;
            margin-bottom: 5px;
        }

        .child-details p {
            font-size: 14px;
            color: rgba(255, 255, 255, 0.9);
        }

        .donation-details {
            background: rgba(255, 255, 255, 0.15);
            border-radius: 20px;
            padding: 25px;
            margin-bottom: 30px;
            border: 1px solid rgba(255, 255, 255, 0.3);
        }

        .detail-row {
            display: flex;
            justify-content: space-between;
            padding: 12px 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.2);
            font-size: 15px;
        }

        .detail-row:last-child {
            border-bottom: none;
        }

        .detail-label {
            color: rgba(255, 255, 255, 0.85);
            font-weight: 500;
        }

        .detail-value {
            color: #ffffff;
            font-weight: 600;
            text-align: right;
            word-break: break-word;
        }

        .amount-highlight {
            font-size: 28px;
            color: #ffffff;
            font-weight: 700;
            text-shadow: 0 2px 10px rgba(0, 0, 0, 0.2);
        }

        .action-buttons {
            display: flex;
            gap: 15px;
            margin-top: 30px;
        }

        .btn {
            flex: 1;
            padding: 16px 30px;
            border: none;
            border-radius: 15px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        .btn-primary {
            background: rgba(255, 255, 255, 0.95);
            color: #11998e;
            box-shadow: 0 4px 15px rgba(255, 255, 255, 0.3);
        }

        .btn-primary:hover {
            background: #ffffff;
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(255, 255, 255, 0.4);
        }

        .btn-secondary {
            background: rgba(255, 255, 255, 0.2);
            color: #ffffff;
            border: 2px solid rgba(255, 255, 255, 0.4);
        }

        .btn-secondary:hover {
            background: rgba(255, 255, 255, 0.3);
            transform: translateY(-2px);
        }

        .download-icon {
            width: 20px;
            height: 20px;
        }

        .footer-message {
            margin-top: 30px;
            padding: 20px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 15px;
            font-size: 14px;
            color: rgba(255, 255, 255, 0.9);
            line-height: 1.6;
        }

        @media (max-width: 600px) {
            body {
                padding: 20px 15px;
            }

            .success-card {
                padding: 40px 25px;
            }

            h1 {
                font-size: 28px;
            }

            .child-info {
                flex-direction: column;
                text-align: center;
            }

            .child-details {
                text-align: center;
            }

            .action-buttons {
                flex-direction: column;
            }

            .detail-row {
                flex-direction: column;
                gap: 5px;
                text-align: center;
            }

            .detail-value {
                text-align: center;
            }
        }
    </style>
</head>
<body>
    <script>
        for(let i = 0; i < 50; i++) {
            let confetti = document.createElement('div');
            confetti.className = 'confetti';
            confetti.style.left = Math.random() * 100 + '%';
            confetti.style.animationDelay = Math.random() * 3 + 's';
            confetti.style.opacity = Math.random();
            document.body.appendChild(confetti);
        }
    </script>

    <div class="container">
        <div class="success-card">
            <div class="success-icon">
                <svg viewBox="0 0 52 52">
                    <path d="M14 27l9 9L38 18"/>
                </svg>
            </div>

            <h1>🎉 Payment Successful!</h1>
            <p class="subtitle">
                Thank you for your generous donation! Your support will make a real difference in a child's life.
            </p>

            <div class="child-info">
                <img src="<?php echo htmlspecialchars($donation['child_photo'] ?: 'default-child.jpg'); ?>" 
                     alt="Child" 
                     class="child-photo">
                <div class="child-details">
                    <h3><?php echo htmlspecialchars($donation['child_first_name'] . ' ' . $donation['child_last_name']); ?></h3>
                    <p>You are now sponsoring this child</p>
                </div>
            </div>

            <div class="donation-details">
                <div class="detail-row">
                    <span class="detail-label">Donation ID</span>
                    <span class="detail-value"><?php echo htmlspecialchars($donation['receipt_no']); ?></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Sponsor Name</span>
                    <span class="detail-value"><?php echo htmlspecialchars($donation['sponsor_first_name'] . ' ' . $donation['sponsor_last_name']); ?></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Email</span>
                    <span class="detail-value"><?php echo htmlspecialchars($donation['sponsor_email']); ?></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Date & Time</span>
                    <span class="detail-value"><?php echo date('d M Y, h:i A', strtotime($donation['payment_date'])); ?></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Payment ID</span>
                    <span class="detail-value"><?php echo htmlspecialchars(substr($donation['razorpay_payment_id'], 0, 20)); ?>...</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Amount Donated</span>
                    <span class="amount-highlight">₹<?php echo number_format($donation['amount'], 2); ?></span>
                </div>
            </div>

            <div class="action-buttons">
                <a href="generate_receipt.php?donation_id=<?php echo urlencode($donation['receipt_no']); ?>" 
                   class="btn btn-primary" 
                   target="_blank">
                    <svg class="download-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                    Download Receipt
                </a>
                <a href="sponser_profile.php" class="btn btn-secondary">
                    Back to Dashboard
                </a>
            </div>

            <div class="footer-message">
                📧 A confirmation email with your receipt has been sent to <strong><?php echo htmlspecialchars($donation['sponsor_email']); ?></strong>
            </div>
        </div>
    </div>
</body>
</html>