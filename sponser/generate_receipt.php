<?php
session_start();
require_once __DIR__ . '/../db_config.php';
require __DIR__ . '/../vendor/autoload.php';

// Check if sponsor is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../signup_and_login/login_template.php");
    exit();
}

// Validate receipt number
if (!isset($_GET['donation_id'])) {
    die("Invalid receipt request");
}

$receipt_no = $_GET['donation_id'];
$user_id = $_SESSION['user_id'];

// FIXED: Get actual sponsor_id from sponsors table
$stmt = $conn->prepare("SELECT sponsor_id FROM sponsors WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$sponsor_result = $stmt->get_result();
$sponsor_data = $sponsor_result->fetch_assoc();

if (!$sponsor_data) {
    die("Sponsor profile not found");
}

$sponsor_id = $sponsor_data['sponsor_id'];

// Fetch donation details - FIXED QUERY
$stmt = $conn->prepare("
    SELECT 
        d.donation_id,
        d.receipt_no,
        d.amount,
        d.payment_date,
        d.razorpay_payment_id,
        d.razorpay_order_id,
        c.first_name AS child_first_name,
        c.last_name AS child_last_name,
        c.gender,
        s.first_name AS sponsor_first_name,
        s.last_name AS sponsor_last_name,
        u.email AS sponsor_email,
        u.phone_no AS sponsor_phone
    FROM donations d
    JOIN children c ON d.child_id = c.child_id
    JOIN sponsors s ON d.sponsor_id = s.sponsor_id
    JOIN users u ON s.user_id = u.user_id
    WHERE d.receipt_no = ? 
    AND d.sponsor_id = ?
    AND d.status = 'Success'
");

$stmt->bind_param("si", $receipt_no, $sponsor_id);
$stmt->execute();
$result = $stmt->get_result();
$donation = $result->fetch_assoc();

if (!$donation) {
    die("Receipt not found or payment not successful");
}

// Create PDF class
class PDF extends FPDF {
    public $donation;
    
    function __construct($donation) {
        parent::__construct();
        $this->donation = $donation;
    }
    
    function Header() {
        // Logo
        $logo_path = __DIR__ . '/../assets/logo.png';
        if (file_exists($logo_path)) {
            $this->Image($logo_path, 15, 10, 25);
        } else {
            // Fallback: Just text if logo doesn't exist
            $this->SetFont('Courier', 'B', 16);
            $this->SetXY(15, 15);
            $this->Cell(0, 8, 'Pari', 0, 1, 'L');
        }
        
        // Header Text
        $this->SetFont('Courier', 'B', 14);
        $this->SetXY(45, 12);
        $this->Cell(0, 8, 'Pari', 0, 1, 'L');
        
        $this->SetFont('Courier', '', 9);
        $this->SetXY(45, 22);
        $this->Cell(0, 5, 'Child Sponsorship Program', 0, 1, 'L');
        $this->SetXY(45, 27);
        $this->Cell(0, 5, 'Making a Difference, One Child at a Time', 0, 1, 'L');
        
        // Horizontal line
        $this->SetDrawColor(0, 0, 0);
        $this->Line(15, 37, 195, 37);
    }
    
    function Footer() {
        $this->SetY(-40);
        $this->SetFont('Courier', '', 7);
        $this->SetDrawColor(0, 0, 0);
        $this->Line(15, $this->GetY(), 195, $this->GetY());
        
        $this->SetY(-35);
        $this->SetFont('Courier', '', 7);
        $this->Cell(0, 4, '>>> Thank you for your generous support <<<', 0, 1, 'C');
        $this->SetFont('Courier', '', 6);
        $this->Cell(0, 3, 'www.sponsolink.org | Email: support@sponsolink.org | Phone: +91-XXXX-XXXX-XX', 0, 1, 'C');
        $this->Cell(0, 3, 'This is a computer-generated receipt. No signature is required.', 0, 1, 'C');
        $this->SetFont('Courier', '', 6);
        $this->SetTextColor(128, 128, 128);
        $this->Cell(0, 3, 'Powered by Sponsolink | ' . date('d-m-Y H:i:s'), 0, 1, 'C');
        $this->SetTextColor(0, 0, 0);
    }
}

// Create PDF instance
$pdf = new PDF($donation);
$pdf->AddPage();
$pdf->SetMargins(15, 40, 15);

// ============ RECEIPT TITLE ============
$pdf->SetFont('Courier', 'B', 14);
$pdf->SetXY(15, 42);
$pdf->Cell(180, 10, 'DONATION RECEIPT', 0, 1, 'C');

// Horizontal line
$pdf->SetDrawColor(0, 0, 0);
$pdf->Line(15, 54, 195, 54);

// ============ RECEIPT INFO SECTION ============
$pdf->SetXY(15, 58);
$pdf->SetFont('Courier', '', 9);

$pdf->SetFont('Courier', 'B', 9);
$pdf->Cell(50, 5, 'RECEIPT NUMBER', 0, 0);
$pdf->SetFont('Courier', '', 9);
$pdf->Cell(0, 5, ': ' . htmlspecialchars($donation['receipt_no']), 0, 1);

$pdf->SetFont('Courier', 'B', 9);
$pdf->Cell(50, 5, 'DATE & TIME', 0, 0);
$pdf->SetFont('Courier', '', 9);
$pdf->Cell(0, 5, ': ' . date('d-m-Y H:i:s', strtotime($donation['payment_date'])), 0, 1);

$pdf->SetFont('Courier', 'B', 9);
$pdf->Cell(50, 5, 'PAYMENT ID', 0, 0);
$pdf->SetFont('Courier', '', 9);
$pdf->Cell(0, 5, ': ' . htmlspecialchars(substr($donation['razorpay_payment_id'], 0, 20)), 0, 1);

$pdf->SetFont('Courier', 'B', 9);
$pdf->Cell(50, 5, 'ORDER ID', 0, 0);
$pdf->SetFont('Courier', '', 9);
$pdf->Cell(0, 5, ': ' . htmlspecialchars(substr($donation['razorpay_order_id'], 0, 20)), 0, 1);

// Horizontal line
$pdf->Line(15, $pdf->GetY() + 2, 195, $pdf->GetY() + 2);

// ============ DONOR INFORMATION ============
$pdf->SetXY(15, $pdf->GetY() + 6);
$pdf->SetFont('Courier', 'B', 10);
$pdf->Cell(0, 5, '[[ DONOR INFORMATION ]]', 0, 1);

$pdf->SetFont('Courier', 'B', 9);
$pdf->Cell(50, 5, 'Name', 0, 0);
$pdf->SetFont('Courier', '', 9);
$pdf->Cell(0, 5, ': ' . htmlspecialchars($donation['sponsor_first_name'] . ' ' . $donation['sponsor_last_name']), 0, 1);

$pdf->SetFont('Courier', 'B', 9);
$pdf->Cell(50, 5, 'Email', 0, 0);
$pdf->SetFont('Courier', '', 9);
$pdf->Cell(0, 5, ': ' . htmlspecialchars($donation['sponsor_email']), 0, 1);

$pdf->SetFont('Courier', 'B', 9);
$pdf->Cell(50, 5, 'Phone', 0, 0);
$pdf->SetFont('Courier', '', 9);
$pdf->Cell(0, 5, ': ' . htmlspecialchars($donation['sponsor_phone']), 0, 1);

// Horizontal line
$pdf->Line(15, $pdf->GetY() + 2, 195, $pdf->GetY() + 2);

// ============ CHILD SPONSORED ============
$pdf->SetXY(15, $pdf->GetY() + 6);
$pdf->SetFont('Courier', 'B', 10);
$pdf->Cell(0, 5, '[[ CHILD SPONSORED ]]', 0, 1);

$pdf->SetFont('Courier', 'B', 9);
$pdf->Cell(50, 5, 'Child Name', 0, 0);
$pdf->SetFont('Courier', '', 9);
$pdf->Cell(0, 5, ': ' . htmlspecialchars($donation['child_first_name'] . ' ' . $donation['child_last_name']), 0, 1);

$pdf->SetFont('Courier', 'B', 9);
$pdf->Cell(50, 5, 'Gender', 0, 0);
$pdf->SetFont('Courier', '', 9);
$pdf->Cell(0, 5, ': ' . htmlspecialchars($donation['gender'] ?: 'N/A'), 0, 1);

// Horizontal line
$pdf->Line(15, $pdf->GetY() + 2, 195, $pdf->GetY() + 2);

// ============ DONATION AMOUNT ============
$pdf->SetXY(15, $pdf->GetY() + 6);
$pdf->SetFont('Courier', 'B', 10);
$pdf->Cell(0, 5, '[[ DONATION DETAILS ]]', 0, 1);

// Amount box
$pdf->SetDrawColor(0, 0, 0);
$pdf->SetLineWidth(1);
$pdf->Rect(15, $pdf->GetY(), 165, 22, '');

$pdf->SetXY(20, $pdf->GetY() + 3);
$pdf->SetFont('Courier', 'B', 9);
$pdf->Cell(50, 5, 'DONATION TYPE', 0, 0);
$pdf->SetFont('Courier', '', 9);
$pdf->Cell(0, 5, ': Child Sponsorship', 0, 1);

$pdf->SetXY(20, $pdf->GetY());
$pdf->SetFont('Courier', 'B', 11);
$pdf->Cell(50, 7, 'AMOUNT DONATED', 0, 0);
$pdf->SetFont('Courier', 'B', 13);
$pdf->SetTextColor(0, 102, 0);
$pdf->Cell(0, 7, ': INR ' . number_format($donation['amount'], 2), 0, 1);
$pdf->SetTextColor(0, 0, 0);

$pdf->SetY($pdf->GetY() + 18);
$pdf->SetLineWidth(0.5);

// ============ TAX BENEFIT INFORMATION ============
$pdf->SetFont('Courier', 'B', 9);
$pdf->Cell(0, 5, '=================================================================', 0, 1, 'C');

$pdf->SetFont('Courier', 'B', 9);
$pdf->Cell(0, 5, 'TAX BENEFIT INFORMATION', 0, 1, 'C');

$pdf->SetFont('Courier', '', 7);
$pdf->MultiCell(180, 4, 'This donation receipt is a valid document for tax purposes under Section 80G of the Indian Income Tax Act. Sponsolink is registered as a non-profit organization eligible for tax deductions. Please retain this receipt for your tax filing. For 80G certificate, please contact our support team.');

// ============ SIGNATURE SECTION ============
$pdf->SetY($pdf->GetY() + 8);
$pdf->SetFont('Courier', '', 9);

// Left column
$pdf->SetXY(20, $pdf->GetY());
$y_sig = $pdf->GetY();
$pdf->Line(20, $y_sig + 20, 70, $y_sig + 20);
$pdf->SetXY(20, $y_sig + 22);
$pdf->SetFont('Courier', 'B', 8);
$pdf->Cell(50, 3, 'Authorized Signature', 0, 1, 'C');

// Right column
$pdf->SetXY(125, $y_sig);
$pdf->Line(125, $y_sig + 20, 175, $y_sig + 20);
$pdf->SetXY(125, $y_sig + 22);
$pdf->Cell(50, 3, 'Sponsolink Seal', 0, 1, 'C');

// ============ GENERATE PDF ============
$filename = 'Receipt_' . str_replace(['/', '-', ':'], '_', $donation['receipt_no']) . '.pdf';
$pdf->Output('D', $filename);
?>