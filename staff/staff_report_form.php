<?php
session_start();

// Authentication check
if (!isset($_SESSION['user_id'])) {
    header("Location: ../signup_and_login/login.php");
    exit();
}

require_once __DIR__ . '/../db_config.php';
require_once __DIR__ . '/../includes/fraud_services.php';
require_once __DIR__ . '/../includes/risk_engine.php';

// Get current user's role
$stmt = $conn->prepare("SELECT user_role FROM users WHERE user_id = ?");
$stmt->bind_param('i', $_SESSION['user_id']);
$stmt->execute();
$logged_in_user = $stmt->get_result()->fetch_assoc();
$user_role = $logged_in_user['user_role'];

// Only Staff and Admin can create reports
if (!in_array($user_role, ['Staff', 'Admin'])) {
    die('Access denied. Only Staff can submit fraud reports.');
}

// Get sponsor_id from URL
if (!isset($_GET['sponsor_id']) || !is_numeric($_GET['sponsor_id'])) {
    die('Invalid sponsor ID');
}

$sponsor_id = intval($_GET['sponsor_id']);

// Get sponsor details
$stmt = $conn->prepare("
    SELECT 
        s.sponsor_id,
        s.first_name,
        s.last_name,
        s.is_flagged,
        s.flag_reason,
        u.email,
        u.phone_no,
        u.created_at as registration_date
    FROM sponsors s
    INNER JOIN users u ON s.user_id = u.user_id
    WHERE s.sponsor_id = ?
");
$stmt->bind_param('i', $sponsor_id);
$stmt->execute();
$sponsor = $stmt->get_result()->fetch_assoc();

if (!$sponsor) {
    die('Sponsor not found');
}

// Get current risk info
$fraud_summary = getSponsorFraudSummary($conn, $sponsor_id);

// Get recent donations for context
$stmt = $conn->prepare("
    SELECT 
        d.donation_id,
        d.amount,
        d.donation_date,
        d.payment_method,
        d.status,
        CONCAT(c.first_name, ' ', c.last_name) as child_name
    FROM donations d
    INNER JOIN children c ON d.child_id = c.child_id
    WHERE d.sponsor_id = ?
    ORDER BY d.donation_date DESC
    LIMIT 10
");
$stmt->bind_param('i', $sponsor_id);
$stmt->execute();
$recent_donations = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Handle form submission
$submission_result = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_report'])) {
    $description = trim($_POST['description']);
    $severity = $_POST['severity'] ?? 'medium';
    $fraud_categories = $_POST['fraud_categories'] ?? [];
    $selected_donation_ids = $_POST['donation_ids'] ?? [];
    
    // Validation
    if (empty($description)) {
        $submission_result = ['success' => false, 'message' => 'Please provide a description of the suspicious activity'];
    } elseif (strlen($description) < 20) {
        $submission_result = ['success' => false, 'message' => 'Description must be at least 20 characters'];
    } elseif (empty($fraud_categories)) {
        $submission_result = ['success' => false, 'message' => 'Please select at least one fraud indicator'];
    } else {
        // Prepare additional data
        $additional_data = [
            'severity' => $severity,
            'fraud_categories' => $fraud_categories,
            'donation_ids' => $selected_donation_ids
        ];
        
        // Prepare additional data with all enhanced fields
        $additional_data = [
            'severity' => $severity,
            'fraud_categories' => $fraud_categories,
            'donation_ids' => $selected_donation_ids
        ];
        
        // Create the staff report with enhanced data
        $submission_result = createStaffReportEnhanced(
            $conn,
            $sponsor_id,
            $_SESSION['user_id'],
            $description,
            $additional_data
        );
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Report Suspicious Activity - <?php echo htmlspecialchars($sponsor['first_name'] . ' ' . $sponsor['last_name']); ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: #ffffff;
            min-height: 100vh;
            padding: 2rem;
            position: relative;
            overflow-x: hidden;
        }

        body::before {
            content: '';
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 150%;
            height: 150%;
            background: radial-gradient(circle at center, rgba(255, 237, 160, 0.8) 0%, rgba(254, 249, 195, 0.6) 15%, rgba(255, 253, 240, 0.4) 30%, transparent 60%);
            pointer-events: none;
            z-index: 0;
            filter: blur(80px);
        }

        .container {
            max-width: 900px;
            margin: 0 auto;
            position: relative;
            z-index: 1;
        }

        .back-btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(254, 240, 138, 0.4);
            color: #3f3f46;
            padding: 0.75rem 1.5rem;
            border-radius: 12px;
            text-decoration: none;
            font-size: 0.95rem;
            font-weight: 600;
            margin-bottom: 2rem;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 0 4px 6px rgba(254, 240, 138, 0.15);
        }

        .back-btn:hover {
            transform: translateX(-5px);
            background: rgba(255, 255, 255, 1);
            box-shadow: 0 8px 16px rgba(254, 240, 138, 0.3);
        }

        .page-header {
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(239, 68, 68, 0.3);
            border-radius: 24px;
            padding: 2.5rem;
            margin-bottom: 2rem;
            box-shadow: 0 8px 32px rgba(239, 68, 68, 0.2);
        }

        .page-title {
            font-size: 2rem;
            font-weight: 800;
            color: #dc2626;
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .page-subtitle {
            font-size: 1rem;
            color: #71717a;
            font-weight: 500;
        }

        .sponsor-info-box {
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(254, 240, 138, 0.3);
            border-radius: 20px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 8px 32px rgba(254, 240, 138, 0.2);
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
        }

        .info-item {
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
        }

        .info-label {
            font-size: 0.75rem;
            color: #71717a;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .info-value {
            font-size: 1rem;
            color: #18181b;
            font-weight: 600;
        }

        .risk-badge {
            display: inline-block;
            padding: 0.5rem 1rem;
            border-radius: 12px;
            font-size: 0.875rem;
            font-weight: 700;
            text-transform: uppercase;
        }

        .risk-badge.normal {
            background: rgba(34, 197, 94, 0.15);
            color: #16a34a;
            border: 1px solid rgba(34, 197, 94, 0.3);
        }

        .risk-badge.watch, .risk-badge.review {
            background: rgba(245, 158, 11, 0.15);
            color: #d97706;
            border: 1px solid rgba(245, 158, 11, 0.3);
        }

        .risk-badge.high, .risk-badge.critical {
            background: rgba(239, 68, 68, 0.15);
            color: #dc2626;
            border: 1px solid rgba(239, 68, 68, 0.3);
        }

        .form-card {
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(254, 240, 138, 0.3);
            border-radius: 24px;
            padding: 2.5rem;
            margin-bottom: 2rem;
            box-shadow: 0 8px 32px rgba(254, 240, 138, 0.2);
        }

        .form-section-title {
            font-size: 1.25rem;
            font-weight: 700;
            color: #18181b;
            margin-bottom: 1.5rem;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-label {
            display: block;
            font-size: 0.95rem;
            font-weight: 600;
            color: #3f3f46;
            margin-bottom: 0.5rem;
        }

        .form-label.required::after {
            content: ' *';
            color: #dc2626;
        }

        .form-input, .form-select, .form-textarea {
            width: 100%;
            padding: 1rem;
            border: 2px solid rgba(254, 240, 138, 0.4);
            border-radius: 12px;
            font-size: 0.95rem;
            font-family: 'Inter', sans-serif;
            background: rgba(255, 255, 255, 0.7);
            transition: all 0.3s;
        }

        .form-input:focus, .form-select:focus, .form-textarea:focus {
            outline: none;
            border-color: rgba(254, 240, 138, 0.8);
            background: rgba(255, 255, 255, 1);
            box-shadow: 0 0 0 4px rgba(254, 249, 195, 0.3);
        }

        .form-textarea {
            min-height: 150px;
            resize: vertical;
        }

        .form-hint {
            font-size: 0.875rem;
            color: #71717a;
            margin-top: 0.5rem;
        }

        /* Severity Radio Buttons */
        .severity-options {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
            gap: 1rem;
            margin-top: 0.5rem;
        }

        .severity-option {
            position: relative;
        }

        .severity-option input[type="radio"] {
            position: absolute;
            opacity: 0;
        }

        .severity-option label {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 1rem;
            border: 2px solid rgba(254, 240, 138, 0.4);
            border-radius: 12px;
            background: rgba(255, 255, 255, 0.7);
            cursor: pointer;
            transition: all 0.3s;
            text-align: center;
        }

        .severity-option input[type="radio"]:checked + label {
            border-color: #fbbf24;
            background: rgba(254, 249, 195, 0.5);
            box-shadow: 0 0 0 4px rgba(254, 249, 195, 0.3);
        }

        .severity-option.low input[type="radio"]:checked + label {
            border-color: #22c55e;
            background: rgba(34, 197, 94, 0.1);
        }

        .severity-option.medium input[type="radio"]:checked + label {
            border-color: #f59e0b;
            background: rgba(245, 158, 11, 0.1);
        }

        .severity-option.high input[type="radio"]:checked + label {
            border-color: #ef4444;
            background: rgba(239, 68, 68, 0.1);
        }

        .severity-option.critical input[type="radio"]:checked + label {
            border-color: #991b1b;
            background: rgba(153, 27, 27, 0.1);
        }

        .severity-icon {
            font-size: 1.5rem;
            margin-bottom: 0.5rem;
        }

        .severity-label {
            font-weight: 600;
            font-size: 0.9rem;
            color: #18181b;
        }

        /* Fraud Categories Checkboxes */
        .fraud-categories {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 0.75rem;
            margin-top: 0.5rem;
        }

        .fraud-category-item {
            display: flex;
            align-items: center;
            padding: 1rem;
            border: 2px solid rgba(254, 240, 138, 0.3);
            border-radius: 12px;
            background: rgba(255, 255, 255, 0.7);
            transition: all 0.3s;
            cursor: pointer;
        }

        .fraud-category-item:hover {
            background: rgba(254, 249, 195, 0.3);
            border-color: rgba(254, 240, 138, 0.5);
        }

        .fraud-category-item input[type="checkbox"] {
            width: 20px;
            height: 20px;
            margin-right: 0.75rem;
            cursor: pointer;
            accent-color: #fbbf24;
        }

        .fraud-category-item label {
            flex: 1;
            font-size: 0.95rem;
            color: #18181b;
            font-weight: 500;
            cursor: pointer;
        }

        .fraud-category-item.checked {
            background: rgba(254, 249, 195, 0.5);
            border-color: #fbbf24;
        }

        /* Donation Checkboxes */
        .donation-list {
            max-height: 300px;
            overflow-y: auto;
            border: 2px solid rgba(254, 240, 138, 0.4);
            border-radius: 12px;
            background: rgba(255, 255, 255, 0.7);
        }

        .donation-item {
            padding: 1rem;
            border-bottom: 1px solid rgba(254, 240, 138, 0.2);
            display: flex;
            align-items: center;
            gap: 1rem;
            transition: background 0.2s;
        }

        .donation-item:hover {
            background: rgba(254, 249, 195, 0.3);
        }

        .donation-item:last-child {
            border-bottom: none;
        }

        .donation-checkbox {
            width: 20px;
            height: 20px;
            cursor: pointer;
            accent-color: #fbbf24;
        }

        .donation-details {
            flex: 1;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .donation-info {
            font-size: 0.95rem;
            color: #18181b;
        }

        .donation-amount {
            font-weight: 700;
            color: #fbbf24;
        }

        .status-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 8px;
            font-size: 0.8rem;
            font-weight: 600;
        }

        .status-badge.success {
            background: rgba(34, 197, 94, 0.15);
            color: #16a34a;
        }

        .status-badge.failed {
            background: rgba(239, 68, 68, 0.15);
            color: #dc2626;
        }

        .btn-group {
            display: flex;
            gap: 1rem;
            margin-top: 2rem;
        }

        .btn {
            flex: 1;
            padding: 1rem 2rem;
            border: none;
            border-radius: 12px;
            font-size: 1rem;
            font-weight: 700;
            font-family: 'Inter', sans-serif;
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .btn-primary {
            background: linear-gradient(135deg, rgba(239, 68, 68, 0.9), rgba(220, 38, 38, 0.8));
            color: white;
            box-shadow: 0 4px 12px rgba(239, 68, 68, 0.3);
        }

        .btn-primary:hover:not(:disabled) {
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(239, 68, 68, 0.5);
        }

        .btn-primary:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .btn-secondary {
            background: rgba(255, 255, 255, 0.9);
            color: #3f3f46;
            border: 2px solid rgba(254, 240, 138, 0.4);
        }

        .btn-secondary:hover {
            background: rgba(255, 255, 255, 1);
            border-color: rgba(254, 240, 138, 0.6);
        }

        .alert {
            padding: 1.5rem;
            border-radius: 16px;
            margin-bottom: 2rem;
            font-weight: 500;
        }

        .alert-success {
            background: rgba(34, 197, 94, 0.15);
            border: 1px solid rgba(34, 197, 94, 0.3);
            color: #16a34a;
        }

        .alert-error {
            background: rgba(239, 68, 68, 0.15);
            border: 1px solid rgba(239, 68, 68, 0.3);
            color: #dc2626;
        }

        .char-counter {
            text-align: right;
            font-size: 0.875rem;
            color: #71717a;
            margin-top: 0.5rem;
        }

        .selection-counter {
            display: inline-block;
            background: rgba(251, 191, 36, 0.2);
            color: #d97706;
            padding: 0.25rem 0.75rem;
            border-radius: 8px;
            font-size: 0.85rem;
            font-weight: 600;
            margin-left: 0.5rem;
        }

        @media (max-width: 768px) {
            body {
                padding: 1rem;
            }

            .btn-group {
                flex-direction: column;
            }

            .info-grid {
                grid-template-columns: 1fr;
            }

            .severity-options {
                grid-template-columns: repeat(2, 1fr);
            }

            .fraud-categories {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <a href="sponsor_profile_staff.php?sponsor_id=<?php echo $sponsor_id; ?>" class="back-btn">
            ← Back to Sponsor Profile
        </a>

        <div class="page-header">
            <h1 class="page-title">
                🚨 Report Suspicious Activity
            </h1>
            <p class="page-subtitle">
                Document concerning behavior or suspicious donation patterns for review by administrators
            </p>
        </div>

        <?php if ($submission_result): ?>
            <div class="alert <?php echo $submission_result['success'] ? 'alert-success' : 'alert-error'; ?>">
                <?php if ($submission_result['success']): ?>
                    <strong>✓ Report Submitted Successfully</strong><br>
                    Signal ID: #<?php echo $submission_result['signal_id']; ?><br>
                    <?php if ($submission_result['case_created']): ?>
                        A fraud case (#<?php echo $submission_result['case_id']; ?>) has been automatically created due to elevated risk score.<br>
                    <?php endif; ?>
                    New Risk Score: <?php echo $submission_result['new_risk']['risk_score']; ?> (<?php echo strtoupper($submission_result['new_risk']['risk_level']); ?>)
                    <br><br>
                    <a href="sponsor_profile_staff.php?sponsor_id=<?php echo $sponsor_id; ?>" style="color: inherit; text-decoration: underline;">Return to Sponsor Profile</a>
                <?php else: ?>
                    <strong>✗ Error:</strong> <?php echo htmlspecialchars($submission_result['message']); ?>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <div class="sponsor-info-box">
            <div class="info-grid">
                <div class="info-item">
                    <span class="info-label">Sponsor Name</span>
                    <span class="info-value"><?php echo htmlspecialchars($sponsor['first_name'] . ' ' . $sponsor['last_name']); ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Email</span>
                    <span class="info-value"><?php echo htmlspecialchars($sponsor['email']); ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Current Risk Level</span>
                    <span class="risk-badge <?php echo $fraud_summary['risk_level']; ?>">
                        <?php echo strtoupper($fraud_summary['risk_level']); ?>
                    </span>
                </div>
                <div class="info-item">
                    <span class="info-label">Risk Score</span>
                    <span class="info-value"><?php echo $fraud_summary['risk_score']; ?> points</span>
                </div>
            </div>
        </div>

        <form method="POST" action="" id="reportForm">
            <div class="form-card">
                <h2 class="form-section-title">1. Severity Level</h2>
                
                <div class="form-group">
                    <label class="form-label required">How severe is this issue?</label>
                    <div class="severity-options">
                        <div class="severity-option low">
                            <input type="radio" name="severity" value="low" id="severity_low">
                            <label for="severity_low">
                                <div class="severity-icon">⚠️</div>
                                <div class="severity-label">Low</div>
                            </label>
                        </div>
                        <div class="severity-option medium">
                            <input type="radio" name="severity" value="medium" id="severity_medium" checked>
                            <label for="severity_medium">
                                <div class="severity-icon">⚡</div>
                                <div class="severity-label">Medium</div>
                            </label>
                        </div>
                        <div class="severity-option high">
                            <input type="radio" name="severity" value="high" id="severity_high">
                            <label for="severity_high">
                                <div class="severity-icon">🔥</div>
                                <div class="severity-label">High</div>
                            </label>
                        </div>
                        <div class="severity-option critical">
                            <input type="radio" name="severity" value="critical" id="severity_critical">
                            <label for="severity_critical">
                                <div class="severity-icon">🚨</div>
                                <div class="severity-label">Critical</div>
                            </label>
                        </div>
                    </div>
                </div>
            </div>

            <div class="form-card">
                <h2 class="form-section-title">2. Fraud Indicators <span class="selection-counter" id="categoryCounter">0 selected</span></h2>

                <div class="form-group">
                    <label class="form-label required">Select all suspicious behaviors observed</label>
                    <div class="fraud-categories">
                        <div class="fraud-category-item">
                            <input type="checkbox" name="fraud_categories[]" value="multiple_failed_payments" id="cat1">
                            <label for="cat1">💳 Multiple failed payment attempts</label>
                        </div>
                        <div class="fraud-category-item">
                            <input type="checkbox" name="fraud_categories[]" value="unusual_amounts" id="cat2">
                            <label for="cat2">💰 Unusually large donation amounts</label>
                        </div>
                        <div class="fraud-category-item">
                            <input type="checkbox" name="fraud_categories[]" value="suspicious_timing" id="cat3">
                            <label for="cat3">⏰ Suspicious timing patterns</label>
                        </div>
                        <div class="fraud-category-item">
                            <input type="checkbox" name="fraud_categories[]" value="contact_violations" id="cat4">
                            <label for="cat4">📧 Contact policy violations</label>
                        </div>
                        <div class="fraud-category-item">
                            <input type="checkbox" name="fraud_categories[]" value="identity_concerns" id="cat5">
                            <label for="cat5">🆔 Identity verification concerns</label>
                        </div>
                        <div class="fraud-category-item">
                            <input type="checkbox" name="fraud_categories[]" value="refund_abuse" id="cat6">
                            <label for="cat6">↩️ Refund/chargeback abuse</label>
                        </div>
                        <div class="fraud-category-item">
                            <input type="checkbox" name="fraud_categories[]" value="multiple_accounts" id="cat7">
                            <label for="cat7">👥 Multiple accounts suspected</label>
                        </div>
                        <div class="fraud-category-item">
                            <input type="checkbox" name="fraud_categories[]" value="stolen_payment" id="cat8">
                            <label for="cat8">🏴‍☠️ Stolen payment method suspected</label>
                        </div>
                        <div class="fraud-category-item">
                            <input type="checkbox" name="fraud_categories[]" value="behavior_changes" id="cat9">
                            <label for="cat9">📊 Sudden behavior changes</label>
                        </div>
                        <div class="fraud-category-item">
                            <input type="checkbox" name="fraud_categories[]" value="other" id="cat10">
                            <label for="cat10">🔍 Other suspicious activity</label>
                        </div>
                    </div>
                    <p class="form-hint" style="margin-top: 1rem;">
                        Select all that apply - this helps categorize and prioritize the investigation
                    </p>
                </div>
            </div>

            <div class="form-card">
                <h2 class="form-section-title">3. Related Donations <span class="selection-counter" id="donationCounter">0 selected</span></h2>

                <div class="form-group">
                    <label class="form-label">Select specific donations related to this report (optional)</label>
                    <p class="form-hint" style="margin-bottom: 1rem;">
                        Check all donations that show suspicious patterns or are part of the concerning behavior
                    </p>
                    
                    <?php if (count($recent_donations) > 0): ?>
                    <div class="donation-list">
                        <?php foreach ($recent_donations as $donation): ?>
                        <div class="donation-item">
                            <input 
                                type="checkbox" 
                                name="donation_ids[]" 
                                value="<?php echo $donation['donation_id']; ?>" 
                                class="donation-checkbox" 
                                id="donation_<?php echo $donation['donation_id']; ?>">
                            <label for="donation_<?php echo $donation['donation_id']; ?>" class="donation-details" style="cursor: pointer;">
                                <div class="donation-info">
                                    <strong><?php echo date('M j, Y', strtotime($donation['donation_date'])); ?></strong>
                                    <span> - <?php echo htmlspecialchars($donation['child_name']); ?></span>
                                    <span class="donation-amount"> - ₹<?php echo number_format($donation['amount'], 2); ?></span>
                                </div>
                                <span class="status-badge <?php echo strtolower($donation['status']); ?>">
                                    <?php echo $donation['status']; ?>
                                </span>
                            </label>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php else: ?>
                    <p style="color: #71717a; font-style: italic;">No recent donations found for this sponsor.</p>
                    <?php endif; ?>
                </div>
            </div>

            <div class="form-card">
                <h2 class="form-section-title">4. Detailed Description</h2>

                <div class="form-group">
                    <label class="form-label required">Describe the suspicious activity in detail</label>
                    <textarea 
                        name="description" 
                        class="form-textarea" 
                        placeholder="Provide a detailed description of what you observed, including dates, times, specific behaviors, and any other relevant context..."
                        required
                        minlength="20"
                        id="descriptionInput"><?php echo isset($_POST['description']) ? htmlspecialchars($_POST['description']) : ''; ?></textarea>
                    <div class="char-counter">
                        <span id="charCount">0</span> / 1000 characters (minimum 20)
                    </div>
                    <p class="form-hint">
                        Include specific details such as: What happened? When did it happen? What makes it suspicious? Any patterns you noticed?
                    </p>
                </div>

                <div class="btn-group">
                    <button type="button" class="btn btn-secondary" onclick="window.history.back()">
                        Cancel
                    </button>
                    <button type="submit" name="submit_report" class="btn btn-primary" id="submitBtn">
                        🚨 Submit Report
                    </button>
                </div>
            </div>
        </form>
    </div>

    <script>
        // Character counter
        const textarea = document.getElementById('descriptionInput');
        const charCount = document.getElementById('charCount');
        
        function updateCharCount() {
            const length = textarea.value.length;
            charCount.textContent = length;
            charCount.style.color = length < 20 ? '#dc2626' : length > 1000 ? '#d97706' : '#71717a';
        }
        
        textarea.addEventListener('input', updateCharCount);
        updateCharCount();

        // Fraud categories counter and styling
        const categoryCheckboxes = document.querySelectorAll('input[name="fraud_categories[]"]');
        const categoryCounter = document.getElementById('categoryCounter');
        
        function updateCategoryCount() {
            const checkedCount = Array.from(categoryCheckboxes).filter(cb => cb.checked).length;
            categoryCounter.textContent = `${checkedCount} selected`;
            
            // Add checked class to parent items
            categoryCheckboxes.forEach(cb => {
                const parent = cb.closest('.fraud-category-item');
                if (cb.checked) {
                    parent.classList.add('checked');
                } else {
                    parent.classList.remove('checked');
                }
            });
        }
        
        categoryCheckboxes.forEach(cb => {
            cb.addEventListener('change', updateCategoryCount);
        });
        updateCategoryCount();

        // Donation counter
        const donationCheckboxes = document.querySelectorAll('input[name="donation_ids[]"]');
        const donationCounter = document.getElementById('donationCounter');
        
        function updateDonationCount() {
            const checkedCount = Array.from(donationCheckboxes).filter(cb => cb.checked).length;
            donationCounter.textContent = `${checkedCount} selected`;
        }
        
        donationCheckboxes.forEach(cb => {
            cb.addEventListener('change', updateDonationCount);
        });
        updateDonationCount();

        // Form validation and submission
        document.getElementById('reportForm').addEventListener('submit', function(e) {
            const description = textarea.value.trim();
            const categoriesChecked = Array.from(categoryCheckboxes).some(cb => cb.checked);
            
            if (description.length < 20) {
                e.preventDefault();
                alert('Please provide a more detailed description (minimum 20 characters)');
                textarea.focus();
                return;
            }
            
            if (!categoriesChecked) {
                e.preventDefault();
                alert('Please select at least one fraud indicator');
                categoryCheckboxes[0].closest('.form-card').scrollIntoView({ behavior: 'smooth' });
                return;
            }
            
            if (!confirm('Are you sure you want to submit this fraud report? This action will be logged and reviewed by administrators.')) {
                e.preventDefault();
            }
        });
    </script>
</body>
</html>