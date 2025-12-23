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

// Get LOGGED-IN user's role (the person viewing this page)
$stmt = $conn->prepare("SELECT user_id, user_role FROM users WHERE user_id = ?");
$stmt->bind_param('i', $_SESSION['user_id']);
$stmt->execute();
$logged_in_user = $stmt->get_result()->fetch_assoc();

if (!$logged_in_user) {
    die('User not found in database. Please login again.');
}

$user_role = $logged_in_user['user_role'];

// Authorization check - Allow Staff, Owner, AND Admin to VIEW sponsor profiles
if (!in_array($user_role, ['Staff', 'Owner', 'Admin'])) {
    die('Access denied. This page is for Staff, Owner, and Admin only. Your role: ' . htmlspecialchars($user_role));
}

// Get sponsor_id from URL (the sponsor whose profile we're viewing)
if (!isset($_GET['sponsor_id']) || !is_numeric($_GET['sponsor_id'])) {
    die('Invalid sponsor ID');
}

$sponsor_id = intval($_GET['sponsor_id']);

// Get complete sponsor profile data
$stmt = $conn->prepare("
    SELECT 
        s.sponsor_id,
        s.first_name,
        s.last_name,
        s.dob,
        s.address,
        s.profile_picture,
        s.is_flagged,
        s.flag_reason,
        u.user_id,
        u.email,
        u.phone_no,
        u.created_at as registration_date,
        TIMESTAMPDIFF(YEAR, s.dob, CURDATE()) as age,
        COUNT(DISTINCT sp.child_id) as children_sponsored
    FROM sponsors s
    INNER JOIN users u ON s.user_id = u.user_id
    LEFT JOIN sponsorships sp ON s.sponsor_id = sp.sponsor_id 
        AND (sp.end_date IS NULL OR sp.end_date > CURDATE())
    WHERE s.sponsor_id = ?
    GROUP BY s.sponsor_id
");
$stmt->bind_param('i', $sponsor_id);
$stmt->execute();
$sponsor = $stmt->get_result()->fetch_assoc();

if (!$sponsor) {
    die('Sponsor not found');
}

// Get fraud data
$fraud_summary = getSponsorFraudSummary($conn, $sponsor_id);
$flag_status = getSponsorFlagStatus($conn, $sponsor_id);

// Get donation history (last 90 days)
$stmt = $conn->prepare("
    SELECT 
        d.donation_id,
        d.amount,
        d.donation_date,
        d.payment_method,
        d.status,
        d.receipt_no,
        CONCAT(c.first_name, ' ', c.last_name) as child_name,
        c.child_id
    FROM donations d
    INNER JOIN children c ON d.child_id = c.child_id
    WHERE d.sponsor_id = ?
    AND d.donation_date >= DATE_SUB(NOW(), INTERVAL 90 DAY)
    ORDER BY d.donation_date DESC
");
$stmt->bind_param('i', $sponsor_id);
$stmt->execute();
$donations = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Calculate donation statistics for graphs
$donation_amounts = [];
$donation_dates = [];
$payment_methods = [];
$success_count = 0;
$failed_count = 0;
$pending_count = 0;

// Monthly aggregation for cleaner charts
$monthly_data = [];
$weekly_frequency = array_fill(0, 7, 0); // Days of week

foreach ($donations as $donation) {
    $donation_amounts[] = $donation['amount'];
    $donation_dates[] = date('M j', strtotime($donation['donation_date']));
    
    // Payment methods
    $method = $donation['payment_method'];
    $payment_methods[$method] = ($payment_methods[$method] ?? 0) + 1;
    
    // Status counts
    if ($donation['status'] === 'Success') $success_count++;
    elseif ($donation['status'] === 'Failed') $failed_count++;
    else $pending_count++;
    
    // Monthly aggregation
    $month_key = date('M Y', strtotime($donation['donation_date']));
    if (!isset($monthly_data[$month_key])) {
        $monthly_data[$month_key] = ['total' => 0, 'count' => 0];
    }
    $monthly_data[$month_key]['total'] += $donation['amount'];
    $monthly_data[$month_key]['count']++;
    
    // Day of week frequency
    $day_of_week = date('w', strtotime($donation['donation_date']));
    $weekly_frequency[$day_of_week]++;
}

// Calculate initials
$initials = strtoupper(substr($sponsor['first_name'], 0, 1) . substr($sponsor['last_name'], 0, 1));

// Count failed payments (fraud indicator)
$stmt = $conn->prepare("
    SELECT COUNT(*) as failed_count
    FROM donations
    WHERE sponsor_id = ?
    AND status = 'Failed'
    AND donation_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)
");
$stmt->bind_param('i', $sponsor_id);
$stmt->execute();
$failed_payments = $stmt->get_result()->fetch_assoc()['failed_count'];

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sponsor Profile - <?php echo htmlspecialchars($sponsor['first_name'] . ' ' . $sponsor['last_name']); ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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

        body::after {
            content: '';
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 100%;
            height: 100%;
            background: radial-gradient(circle at center, rgba(255, 243, 176, 0.5) 0%, transparent 50%);
            pointer-events: none;
            z-index: 0;
            filter: blur(120px);
        }

        .container {
            max-width: 1400px;
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

        .fraud-alert {
            background: linear-gradient(135deg, rgba(239, 68, 68, 0.15), rgba(220, 38, 38, 0.1));
            border: 2px solid rgba(239, 68, 68, 0.4);
            border-radius: 20px;
            padding: 2rem;
            margin-bottom: 2rem;
            backdrop-filter: blur(20px);
            box-shadow: 0 8px 32px rgba(239, 68, 68, 0.2);
        }

        .fraud-alert.high {
            background: linear-gradient(135deg, rgba(245, 158, 11, 0.15), rgba(217, 119, 6, 0.1));
            border-color: rgba(245, 158, 11, 0.4);
        }

        .fraud-alert.critical {
            background: linear-gradient(135deg, rgba(239, 68, 68, 0.25), rgba(220, 38, 38, 0.15));
            border-color: rgba(239, 68, 68, 0.6);
        }

        .alert-header {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .alert-icon {
            font-size: 2rem;
        }

        .alert-title {
            font-size: 1.5rem;
            font-weight: 800;
            color: #991b1b;
        }

        .alert-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-top: 1rem;
        }

        .alert-stat {
            background: rgba(255, 255, 255, 0.6);
            padding: 1rem;
            border-radius: 12px;
        }

        .alert-stat-label {
            font-size: 0.75rem;
            color: #71717a;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: 0.5rem;
        }

        .alert-stat-value {
            font-size: 1.5rem;
            font-weight: 800;
            color: #18181b;
        }

        .profile-header {
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(254, 240, 138, 0.3);
            border-radius: 24px;
            padding: 2.5rem;
            margin-bottom: 2rem;
            box-shadow: 0 8px 32px rgba(254, 240, 138, 0.2);
        }

        .profile-top {
            display: grid;
            grid-template-columns: auto 1fr auto;
            gap: 2rem;
            align-items: start;
        }

        .profile-photo {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            background: linear-gradient(135deg, rgba(254, 249, 195, 0.9), rgba(253, 230, 138, 0.8));
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.5rem;
            color: #3f3f46;
            font-weight: 700;
            box-shadow: 0 8px 24px rgba(254, 240, 138, 0.4);
            overflow: hidden;
        }

        .profile-photo img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .profile-info h1 {
            font-size: 2rem;
            font-weight: 800;
            color: #18181b;
            margin-bottom: 0.5rem;
            letter-spacing: -0.02em;
        }

        .profile-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 1.5rem;
            margin-top: 1rem;
            font-size: 0.95rem;
            color: #71717a;
        }

        .meta-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .meta-icon {
            font-size: 1.2rem;
        }

        .report-btn {
            padding: 1rem 2rem;
            background: linear-gradient(135deg, rgba(239, 68, 68, 0.9), rgba(220, 38, 38, 0.8));
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 0.95rem;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 0 4px 12px rgba(239, 68, 68, 0.3);
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            white-space: nowrap;
        }

        .report-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(239, 68, 68, 0.5);
            background: linear-gradient(135deg, rgba(239, 68, 68, 1), rgba(220, 38, 38, 0.9));
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(254, 240, 138, 0.3);
            border-radius: 20px;
            padding: 2rem;
            box-shadow: 0 8px 32px rgba(254, 240, 138, 0.2);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 12px 48px rgba(254, 240, 138, 0.35);
        }

        .stat-label {
            font-size: 0.875rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #71717a;
            margin-bottom: 0.75rem;
        }

        .stat-value {
            font-size: 2.5rem;
            font-weight: 800;
            letter-spacing: -0.02em;
            color: #18181b;
        }

        .stat-subtext {
            font-size: 0.875rem;
            color: #71717a;
            margin-top: 0.5rem;
        }

        .section-card {
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(254, 240, 138, 0.3);
            border-radius: 24px;
            padding: 2.5rem;
            margin-bottom: 2rem;
            box-shadow: 0 8px 32px rgba(254, 240, 138, 0.2);
        }

        .section-title {
            font-size: 1.5rem;
            font-weight: 800;
            color: #18181b;
            margin-bottom: 1.5rem;
            letter-spacing: -0.01em;
        }

        .donation-table {
            width: 100%;
            border-collapse: collapse;
        }

        .donation-table thead {
            background: rgba(254, 249, 195, 0.3);
        }

        .donation-table th {
            padding: 1rem;
            text-align: left;
            font-size: 0.875rem;
            font-weight: 700;
            color: #3f3f46;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .donation-table td {
            padding: 1rem;
            border-bottom: 1px solid rgba(254, 240, 138, 0.2);
            font-size: 0.95rem;
            color: #18181b;
        }

        .donation-table tr:hover {
            background: rgba(254, 249, 195, 0.15);
        }

        .status-badge {
            display: inline-block;
            padding: 0.375rem 0.75rem;
            border-radius: 8px;
            font-size: 0.8rem;
            font-weight: 600;
        }

        .status-badge.success {
            background: rgba(34, 197, 94, 0.15);
            color: #16a34a;
            border: 1px solid rgba(34, 197, 94, 0.3);
        }

        .status-badge.failed {
            background: rgba(239, 68, 68, 0.15);
            color: #dc2626;
            border: 1px solid rgba(239, 68, 68, 0.3);
        }

        .status-badge.pending {
            background: rgba(245, 158, 11, 0.15);
            color: #d97706;
            border: 1px solid rgba(245, 158, 11, 0.3);
        }

        /* IMPROVED CHARTS - Smaller & Better Layout */
        .charts-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .chart-container {
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(254, 240, 138, 0.3);
            border-radius: 20px;
            padding: 1.5rem;
            box-shadow: 0 8px 32px rgba(254, 240, 138, 0.2);
            height: 320px;
        }

        .chart-container.full-width {
            grid-column: 1 / -1;
        }

        .chart-title {
            font-size: 1.1rem;
            font-weight: 700;
            color: #18181b;
            margin-bottom: 1rem;
        }

        .chart-wrapper {
            height: calc(100% - 40px);
            position: relative;
        }

        .fraud-indicators {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
        }

        .indicator-card {
            background: rgba(255, 255, 255, 0.7);
            padding: 1.5rem;
            border-radius: 16px;
            border: 1px solid rgba(254, 240, 138, 0.3);
        }

        .indicator-title {
            font-size: 0.875rem;
            color: #71717a;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }

        .indicator-value {
            font-size: 1.5rem;
            font-weight: 800;
            color: #18181b;
        }

        .indicator-value.warning {
            color: #d97706;
        }

        .indicator-value.danger {
            color: #dc2626;
        }

        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            color: #71717a;
        }

        .empty-icon {
            font-size: 4rem;
            margin-bottom: 1rem;
            opacity: 0.5;
        }

        @media (max-width: 1024px) {
            .charts-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 768px) {
            body {
                padding: 1rem;
            }

            .profile-top {
                grid-template-columns: 1fr;
                text-align: center;
            }

            .profile-photo {
                margin: 0 auto;
            }

            .report-btn {
                width: 100%;
                justify-content: center;
            }

            .profile-meta {
                justify-content: center;
            }

            .chart-container {
                height: 280px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <?php 
        // Determine correct back URL based on user role
        $back_url = 'total_sponsors.php';
        if ($user_role === 'Staff') {
            $back_url = 'staff_home_old.php'; // or whatever your staff dashboard is
        } elseif ($user_role === 'Owner' || $user_role === 'Admin') {
            $back_url = '../owner/owner_home.php';
        }
        ?>
        <a href="<?php echo $back_url; ?>" class="back-btn">
            ← Back to Dashboard
        </a>

        <?php if ($sponsor['is_flagged'] || $fraud_summary['risk_level'] !== 'normal'): ?>
        <div class="fraud-alert <?php echo $fraud_summary['risk_level']; ?>">
            <div class="alert-header">
                <span class="alert-icon">⚠️</span>
                <div>
                    <h2 class="alert-title">
                        <?php 
                        if ($fraud_summary['risk_level'] === 'critical') {
                            echo 'CRITICAL RISK ALERT';
                        } elseif ($fraud_summary['risk_level'] === 'high') {
                            echo 'HIGH RISK ALERT';
                        } else {
                            echo 'ACCOUNT UNDER REVIEW';
                        }
                        ?>
                    </h2>
                    <?php if ($sponsor['flag_reason']): ?>
                    <p style="color: #71717a; margin-top: 0.5rem;">
                        <?php echo htmlspecialchars($sponsor['flag_reason']); ?>
                    </p>
                    <?php endif; ?>
                </div>
            </div>
            <div class="alert-details">
                <div class="alert-stat">
                    <div class="alert-stat-label">Risk Level</div>
                    <div class="alert-stat-value"><?php echo strtoupper($fraud_summary['risk_level']); ?></div>
                </div>
                <div class="alert-stat">
                    <div class="alert-stat-label">Risk Score</div>
                    <div class="alert-stat-value"><?php echo $fraud_summary['risk_score']; ?></div>
                </div>
                <div class="alert-stat">
                    <div class="alert-stat-label">Active Signals</div>
                    <div class="alert-stat-value">
                        <?php 
                        $total_signals = 0;
                        foreach ($fraud_summary['signal_breakdown'] as $signal) {
                            $total_signals += $signal['count'];
                        }
                        echo $total_signals;
                        ?>
                    </div>
                </div>
                <?php if ($fraud_summary['has_active_case']): ?>
                <div class="alert-stat">
                    <div class="alert-stat-label">Case Status</div>
                    <div class="alert-stat-value" style="font-size: 1rem;">
                        <?php echo strtoupper(str_replace('_', ' ', $fraud_summary['active_case']['status'])); ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <div class="profile-header">
            <div class="profile-top">
                <div class="profile-photo">
                    <?php if (!empty($sponsor['profile_picture']) && file_exists('../' . $sponsor['profile_picture'])): ?>
                        <img src="../<?php echo htmlspecialchars($sponsor['profile_picture']); ?>" alt="Profile Picture">
                    <?php else: ?>
                        <?php echo $initials; ?>
                    <?php endif; ?>
                </div>

                <div class="profile-info">
                    <h1><?php echo htmlspecialchars($sponsor['first_name'] . ' ' . $sponsor['last_name']); ?></h1>
                    <div class="profile-meta">
                        <div class="meta-item">
                            <span class="meta-icon">📧</span>
                            <span><?php echo htmlspecialchars($sponsor['email']); ?></span>
                        </div>
                        <div class="meta-item">
                            <span class="meta-icon">📱</span>
                            <span><?php echo htmlspecialchars($sponsor['phone_no'] ?: 'N/A'); ?></span>
                        </div>
                        <div class="meta-item">
                            <span class="meta-icon">🎂</span>
                            <span><?php echo $sponsor['age']; ?> years old</span>
                        </div>
                        <div class="meta-item">
                            <span class="meta-icon">📍</span>
                            <span><?php echo htmlspecialchars($sponsor['address'] ?: 'N/A'); ?></span>
                        </div>
                        <div class="meta-item">
                            <span class="meta-icon">📅</span>
                            <span>Member since <?php echo date('F Y', strtotime($sponsor['registration_date'])); ?></span>
                        </div>
                    </div>
                </div>

                <?php if ($user_role === 'Staff'): ?>
                <a href="staff_report_form.php?sponsor_id=<?php echo $sponsor_id; ?>" class="report-btn">
                    🚨 Report Suspicious Activity
                </a>
                <?php endif; ?>
            </div>
        </div>

        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-label">Children Sponsored</div>
                <div class="stat-value"><?php echo $sponsor['children_sponsored']; ?></div>
                <div class="stat-subtext">Currently active sponsorships</div>
            </div>

            <div class="stat-card">
                <div class="stat-label">Total Donations</div>
                <div class="stat-value">
                    ₹<?php echo number_format(array_sum($donation_amounts), 2); ?>
                </div>
                <div class="stat-subtext">Last 90 days</div>
            </div>

            <div class="stat-card">
                <div class="stat-label">Donation Count</div>
                <div class="stat-value"><?php echo count($donations); ?></div>
                <div class="stat-subtext">Transactions in 90 days</div>
            </div>

            <div class="stat-card">
                <div class="stat-label">Average Donation</div>
                <div class="stat-value">
                    ₹<?php echo count($donations) > 0 ? number_format(array_sum($donation_amounts) / count($donations), 2) : '0.00'; ?>
                </div>
                <div class="stat-subtext">Per transaction</div>
            </div>
        </div>

        <?php if (count($donations) > 0): ?>
        <div class="charts-grid">
            <!-- Chart 1: Donation Amount Trend (Line) -->
            <div class="chart-container">
                <h3 class="chart-title">💰 Donation Trend</h3>
                <div class="chart-wrapper">
                    <canvas id="amountChart"></canvas>
                </div>
            </div>

            <!-- Chart 2: Payment Success Rate (Doughnut) -->
            <div class="chart-container">
                <h3 class="chart-title">✅ Payment Success Rate</h3>
                <div class="chart-wrapper">
                    <canvas id="successRateChart"></canvas>
                </div>
            </div>

            <!-- Chart 3: Weekly Donation Pattern (Bar) -->
            <div class="chart-container">
                <h3 class="chart-title">📊 Donation Pattern by Day</h3>
                <div class="chart-wrapper">
                    <canvas id="weeklyPatternChart"></canvas>
                </div>
            </div>

            <!-- Chart 4: Payment Methods (Pie) -->
            <div class="chart-container">
                <h3 class="chart-title">💳 Payment Methods</h3>
                <div class="chart-wrapper">
                    <canvas id="paymentMethodChart"></canvas>
                </div>
            </div>

            <!-- Chart 5: Monthly Donation Volume (Bar) - Full Width -->
            <div class="chart-container full-width">
                <h3 class="chart-title">📈 Monthly Donation Volume</h3>
                <div class="chart-wrapper">
                    <canvas id="monthlyVolumeChart"></canvas>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <div class="section-card">
            <h2 class="section-title">Fraud Detection Indicators</h2>
            <div class="fraud-indicators">
                <div class="indicator-card">
                    <div class="indicator-title">Failed Payments (30 days)</div>
                    <div class="indicator-value <?php echo $failed_payments >= 3 ? 'danger' : ($failed_payments > 0 ? 'warning' : ''); ?>">
                        <?php echo $failed_payments; ?>
                    </div>
                </div>

                <div class="indicator-card">
                    <div class="indicator-title">Auto-Generated Signals</div>
                    <div class="indicator-value">
                        <?php 
                        $auto_count = 0;
                        foreach ($fraud_summary['signal_breakdown'] as $signal) {
                            if (strpos($signal['signal_type'], 'auto_') === 0) {
                                $auto_count += $signal['count'];
                            }
                        }
                        echo $auto_count;
                        ?>
                    </div>
                </div>

                <div class="indicator-card">
                    <div class="indicator-title">Staff Reports</div>
                    <div class="indicator-value">
                        <?php 
                        $staff_count = 0;
                        foreach ($fraud_summary['signal_breakdown'] as $signal) {
                            if ($signal['signal_type'] === 'staff_report') {
                                $staff_count = $signal['count'];
                            }
                        }
                        echo $staff_count;
                        ?>
                    </div>
                </div>

                <div class="indicator-card">
                    <div class="indicator-title">Risk Weight Total</div>
                    <div class="indicator-value <?php echo $fraud_summary['risk_score'] >= 60 ? 'danger' : ($fraud_summary['risk_score'] >= 40 ? 'warning' : ''); ?>">
                        <?php echo $fraud_summary['risk_score']; ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="section-card">
            <h2 class="section-title">Donation History (Last 90 Days)</h2>
            <?php if (count($donations) > 0): ?>
            <table class="donation-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Child</th>
                        <th>Amount</th>
                        <th>Payment Method</th>
                        <th>Status</th>
                        <th>Receipt</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($donations as $donation): ?>
                    <tr>
                        <td><?php echo date('M j, Y', strtotime($donation['donation_date'])); ?></td>
                        <td><?php echo htmlspecialchars($donation['child_name']); ?></td>
                        <td>₹<?php echo number_format($donation['amount'], 2); ?></td>
                        <td><?php echo htmlspecialchars($donation['payment_method']); ?></td>
                        <td>
                            <span class="status-badge <?php echo strtolower($donation['status']); ?>">
                                <?php echo $donation['status']; ?>
                            </span>
                        </td>
                        <td><?php echo htmlspecialchars($donation['receipt_no'] ?: 'N/A'); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?>
            <div class="empty-state">
                <div class="empty-icon">📭</div>
                <p>No donations in the last 90 days</p>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <?php if (count($donations) > 0): ?>
    <script>
        Chart.defaults.font.family = 'Inter';
        Chart.defaults.font.size = 12;

        // Chart 1: Donation Amount Trend
        const amountCtx = document.getElementById('amountChart').getContext('2d');
        new Chart(amountCtx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode(array_slice(array_reverse($donation_dates), 0, 20)); ?>,
                datasets: [{
                    label: 'Amount (₹)',
                    data: <?php echo json_encode(array_slice(array_reverse($donation_amounts), 0, 20)); ?>,
                    borderColor: '#fbbf24',
                    backgroundColor: 'rgba(251, 191, 36, 0.1)',
                    borderWidth: 2,
                    fill: true,
                    tension: 0.4,
                    pointRadius: 3,
                    pointHoverRadius: 5
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return '₹' + value;
                            }
                        }
                    },
                    x: {
                        ticks: {
                            maxRotation: 45,
                            minRotation: 45
                        }
                    }
                }
            }
        });

        // Chart 2: Payment Success Rate
        const successCtx = document.getElementById('successRateChart').getContext('2d');
        new Chart(successCtx, {
            type: 'doughnut',
            data: {
                labels: ['Success', 'Failed', 'Pending'],
                datasets: [{
                    data: [<?php echo $success_count; ?>, <?php echo $failed_count; ?>, <?php echo $pending_count; ?>],
                    backgroundColor: [
                        'rgba(34, 197, 94, 0.8)',
                        'rgba(239, 68, 68, 0.8)',
                        'rgba(245, 158, 11, 0.8)'
                    ],
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            padding: 10,
                            font: { size: 11 }
                        }
                    }
                }
            }
        });

        // Chart 3: Weekly Donation Pattern
        const weeklyCtx = document.getElementById('weeklyPatternChart').getContext('2d');
        new Chart(weeklyCtx, {
            type: 'bar',
            data: {
                labels: ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'],
                datasets: [{
                    label: 'Donations',
                    data: <?php echo json_encode($weekly_frequency); ?>,
                    backgroundColor: 'rgba(251, 191, 36, 0.7)',
                    borderColor: '#fbbf24',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: { stepSize: 1 }
                    }
                }
            }
        });

        // Chart 4: Payment Methods
        const paymentCtx = document.getElementById('paymentMethodChart').getContext('2d');
        new Chart(paymentCtx, {
            type: 'pie',
            data: {
                labels: <?php echo json_encode(array_keys($payment_methods)); ?>,
                datasets: [{
                    data: <?php echo json_encode(array_values($payment_methods)); ?>,
                    backgroundColor: [
                        'rgba(251, 191, 36, 0.8)',
                        'rgba(254, 249, 195, 0.8)',
                        'rgba(253, 230, 138, 0.8)',
                        'rgba(252, 211, 77, 0.8)'
                    ],
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            padding: 10,
                            font: { size: 11 }
                        }
                    }
                }
            }
        });

        // Chart 5: Monthly Volume
        const monthlyCtx = document.getElementById('monthlyVolumeChart').getContext('2d');
        new Chart(monthlyCtx, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode(array_keys($monthly_data)); ?>,
                datasets: [{
                    label: 'Total Amount (₹)',
                    data: <?php echo json_encode(array_column($monthly_data, 'total')); ?>,
                    backgroundColor: 'rgba(251, 191, 36, 0.7)',
                    borderColor: '#fbbf24',
                    borderWidth: 1,
                    yAxisID: 'y'
                }, {
                    label: 'Donation Count',
                    data: <?php echo json_encode(array_column($monthly_data, 'count')); ?>,
                    backgroundColor: 'rgba(59, 130, 246, 0.7)',
                    borderColor: '#3b82f6',
                    borderWidth: 1,
                    yAxisID: 'y1'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'top',
                        labels: {
                            font: { size: 11 }
                        }
                    }
                },
                scales: {
                    y: {
                        type: 'linear',
                        display: true,
                        position: 'left',
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return '₹' + value;
                            }
                        }
                    },
                    y1: {
                        type: 'linear',
                        display: true,
                        position: 'right',
                        beginAtZero: true,
                        grid: {
                            drawOnChartArea: false
                        }
                    }
                }
            }
        });
    </script>
    <?php endif; ?>
</body>
</html>