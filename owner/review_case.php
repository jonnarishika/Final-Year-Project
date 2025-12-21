<?php
// review_case.php - Admin case review and decision page
session_start();
require_once __DIR__ . '/../db_config.php';
require_once __DIR__ . '/../includes/fraud_services.php';
require_once __DIR__ . '/../includes/risk_engine.php';



$sponsor_id = $_GET['sponsor_id'] ?? null;
if (!$sponsor_id) {
    header('Location: signals_overview.php');
    exit();
}

// Handle admin action submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    $justification = $_POST['justification'] ?? '';
    
    $result = adminTakeAction($conn, $sponsor_id, $_SESSION['user_id'], $action, $justification);
    
    if ($result['success']) {
        $success_message = "Action '{$action}' completed successfully.";
    } else {
        $error_message = $result['error'];
    }
}

// Get complete case details
$case_data = getFraudCaseDetails($conn, $sponsor_id);

if (!$case_data) {
    die("Sponsor not found");
}

$sponsor = $case_data['sponsor'];
$risk = $case_data['risk'];
$signals = $case_data['signals'];
$active_case = $case_data['active_case'];
$case_notes = $case_data['case_notes'];
$donations = $case_data['donations'];
$appeals = $case_data['appeals'];

// Prepare data for charts
$donation_dates = [];
$donation_amounts = [];
$donation_statuses = [];

foreach ($donations as $donation) {
    $donation_dates[] = date('M j', strtotime($donation['donation_date']));
    $donation_amounts[] = (float)$donation['amount'];
    $donation_statuses[] = $donation['status'];
}

// Calculate statistics
$total_donated = array_sum($donation_amounts);
$avg_donation = count($donation_amounts) > 0 ? $total_donated / count($donation_amounts) : 0;
$failed_count = count(array_filter($donation_statuses, fn($s) => $s === 'Failed'));
$success_count = count(array_filter($donation_statuses, fn($s) => $s === 'Success'));

// Get initials
function getInitials($name) {
    $parts = explode(' ', $name);
    return strtoupper(substr($parts[0], 0, 1) . (isset($parts[1]) ? substr($parts[1], 0, 1) : ''));
}

function getRiskBadge($level) {
    $badges = [
        'normal' => 'success',
        'watch' => 'info',
        'review' => 'warning',
        'high' => 'danger',
        'critical' => 'danger'
    ];
    return $badges[$level] ?? 'secondary';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Review Case - <?= htmlspecialchars($sponsor['first_name'] . ' ' . $sponsor['last_name']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <style>
        :root {
            --bg-pink: #FFE5E5;
            --card-pink: #FFF0F0;
            --accent-pink: #FFB3B3;
            --text-dark: #2C2C2C;
            --border-light: #FFD4D4;
        }
        
        body {
            background: linear-gradient(135deg, #FFE5E5 0%, #FFF5F5 100%);
            min-height: 100vh;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }
        
        .back-button {
            background: white;
            border: 2px solid var(--border-light);
            color: var(--text-dark);
            padding: 0.5rem 1.5rem;
            border-radius: 50px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s ease;
            display: inline-block;
            margin-bottom: 1.5rem;
        }
        
        .back-button:hover {
            background: var(--card-pink);
            border-color: var(--accent-pink);
            color: var(--text-dark);
        }
        
        .main-header {
            background: white;
            border-radius: 20px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 4px 15px rgba(255, 179, 179, 0.1);
        }
        
        .sponsor-profile {
            display: flex;
            align-items: center;
            gap: 1.5rem;
        }
        
        .sponsor-avatar-large {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: linear-gradient(135deg, #FFB3B3 0%, #FF9999 100%);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 2rem;
            box-shadow: 0 4px 15px rgba(255, 179, 179, 0.3);
        }
        
        .risk-score-display {
            background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
            color: white;
            padding: 1rem 2rem;
            border-radius: 16px;
            text-align: center;
        }
        
        .risk-score-display h3 {
            font-size: 3rem;
            font-weight: 700;
            margin: 0;
        }
        
        .card-custom {
            background: white;
            border-radius: 16px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 2px 10px rgba(255, 179, 179, 0.08);
            border: 1px solid var(--border-light);
        }
        
        .section-title {
            font-weight: 700;
            color: var(--text-dark);
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .chart-container {
            position: relative;
            height: 300px;
            margin: 1rem 0;
        }
        
        .stat-box {
            background: var(--card-pink);
            border-radius: 12px;
            padding: 1rem;
            text-align: center;
            border: 2px solid var(--border-light);
        }
        
        .stat-box h4 {
            font-size: 1.8rem;
            font-weight: 700;
            margin: 0.5rem 0;
            color: var(--text-dark);
        }
        
        .signal-item {
            background: var(--card-pink);
            border-radius: 12px;
            padding: 1rem;
            margin-bottom: 0.75rem;
            border-left: 4px solid;
        }
        
        .signal-item.system {
            border-left-color: #0d6efd;
        }
        
        .signal-item.staff {
            border-left-color: #ffc107;
        }
        
        .donation-item {
            background: white;
            border: 2px solid var(--border-light);
            border-radius: 12px;
            padding: 1rem;
            margin-bottom: 0.75rem;
            transition: all 0.3s ease;
        }
        
        .donation-item:hover {
            border-color: var(--accent-pink);
            box-shadow: 0 2px 8px rgba(255, 179, 179, 0.15);
        }
        
        .action-buttons {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
        }
        
        .action-btn {
            flex: 1;
            padding: 1rem;
            border-radius: 12px;
            border: none;
            font-weight: 600;
            transition: all 0.3s ease;
            cursor: pointer;
            min-width: 150px;
        }
        
        .action-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }
        
        .btn-clear {
            background: linear-gradient(135deg, #28a745 0%, #218838 100%);
            color: white;
        }
        
        .btn-restrict {
            background: linear-gradient(135deg, #ffc107 0%, #e0a800 100%);
            color: white;
        }
        
        .btn-freeze {
            background: linear-gradient(135deg, #fd7e14 0%, #e86d0d 100%);
            color: white;
        }
        
        .btn-block {
            background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
            color: white;
        }
        
        .justification-box {
            background: var(--card-pink);
            border: 2px solid var(--border-light);
            border-radius: 12px;
            padding: 1rem;
            width: 100%;
            resize: vertical;
            min-height: 100px;
            font-family: inherit;
        }
        
        .justification-box:focus {
            outline: none;
            border-color: var(--accent-pink);
            box-shadow: 0 0 0 3px rgba(255, 179, 179, 0.1);
        }
        
        .alert-custom {
            border-radius: 12px;
            border: none;
            padding: 1rem 1.5rem;
        }
        
        .case-note {
            background: white;
            border-left: 4px solid var(--accent-pink);
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 0.75rem;
        }
        
        .appeal-card {
            background: #fff9e6;
            border: 2px solid #ffe066;
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 1rem;
        }
        
        .badge-custom {
            padding: 0.4rem 1rem;
            border-radius: 50px;
            font-weight: 600;
        }
        
        .timeline-item {
            position: relative;
            padding-left: 2rem;
            margin-bottom: 1rem;
        }
        
        .timeline-item::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background: var(--accent-pink);
        }
    </style>
</head>
<body>
    <div class="container-fluid px-4 py-4">
        <a href="signals_overview.php" class="back-button">
            <i class="bi bi-arrow-left"></i> Back to Signals
        </a>
        
        <?php if (isset($success_message)): ?>
            <div class="alert alert-success alert-custom">
                <i class="bi bi-check-circle-fill"></i> <?= $success_message ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($error_message)): ?>
            <div class="alert alert-danger alert-custom">
                <i class="bi bi-exclamation-triangle-fill"></i> <?= $error_message ?>
            </div>
        <?php endif; ?>

        <!-- Sponsor Profile Header -->
        <div class="main-header">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <div class="sponsor-profile">
                        <div>
                            <p class="text-muted mb-1">
                                <i class="bi bi-envelope"></i> <?= htmlspecialchars($sponsor['email']) ?>
                            </p>
                            <p class="text-muted mb-0">
                                <i class="bi bi-calendar"></i> Joined: <?= date('M j, Y', strtotime($sponsor['account_created'])) ?>
                            </p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="risk-score-display">
                        <small>Current Risk Score</small>
                        <h3><?= $risk['risk_score'] ?></h3>
                        <span class="badge bg-light text-dark"><?= strtoupper($risk['risk_level']) ?></span>
                    </div>
                </div>
                <div class="col-md-3">
                    <?php if ($sponsor['is_flagged']): ?>
                        <div class="alert alert-danger mb-0">
                            <i class="bi bi-flag-fill"></i> <strong>FLAGGED</strong><br>
                            <small><?= htmlspecialchars($sponsor['flag_reason']) ?></small>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-success mb-0">
                            <i class="bi bi-check-circle-fill"></i> <strong>Not Flagged</strong><br>
                            <small>Account in good standing</small>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Left Column: Charts & Donations -->
            <div class="col-lg-8">
                <!-- Payment Analytics -->
                <div class="card-custom">
                    <h4 class="section-title">
                        <i class="bi bi-graph-up"></i> Payment Analytics (Last 90 Days)
                    </h4>
                    
                    <div class="row mb-3">
                        <div class="col-md-4">
                            <div class="stat-box">
                                <small class="text-muted">Total Donated</small>
                                <h4>₹<?= number_format($total_donated, 2) ?></h4>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="stat-box">
                                <small class="text-muted">Average Amount</small>
                                <h4>₹<?= number_format($avg_donation, 2) ?></h4>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="stat-box">
                                <small class="text-muted">Success Rate</small>
                                <h4><?= count($donations) > 0 ? round(($success_count / count($donations)) * 100, 1) : 0 ?>%</h4>
                            </div>
                        </div>
                    </div>

                    <!-- Donation Amount Chart -->
                    <div class="chart-container">
                        <canvas id="donationChart"></canvas>
                    </div>
                </div>

                <!-- Payment Frequency Chart -->
                <div class="card-custom">
                    <h4 class="section-title">
                        <i class="bi bi-bar-chart"></i> Payment Frequency & Status
                    </h4>
                    <div class="chart-container">
                        <canvas id="frequencyChart"></canvas>
                    </div>
                </div>

                <!-- Recent Donations -->
                <div class="card-custom">
                    <h4 class="section-title">
                        <i class="bi bi-list-ul"></i> Recent Donations (<?= count($donations) ?>)
                    </h4>
                    
                    <?php if (empty($donations)): ?>
                        <p class="text-muted">No donations in the last 90 days.</p>
                    <?php else: ?>
                        <div style="max-height: 400px; overflow-y: auto;">
                            <?php foreach ($donations as $donation): ?>
                                <div class="donation-item">
                                    <div class="row align-items-center">
                                        <div class="col-md-3">
                                            <strong>₹<?= number_format($donation['amount'], 2) ?></strong><br>
                                            <small class="text-muted"><?= date('M j, Y g:i A', strtotime($donation['donation_date'])) ?></small>
                                        </div>
                                        <div class="col-md-4">
                                            <small class="text-muted">To:</small> <?= htmlspecialchars($donation['child_name']) ?><br>
                                            <small class="text-muted">Method:</small> <?= $donation['payment_method'] ?>
                                        </div>
                                        <div class="col-md-3">
                                            <?php if ($donation['razorpay_payment_id']): ?>
                                                <small class="text-muted">Payment ID:</small><br>
                                                <code style="font-size: 0.75rem;"><?= substr($donation['razorpay_payment_id'], 0, 20) ?>...</code>
                                            <?php endif; ?>
                                        </div>
                                        <div class="col-md-2 text-end">
                                            <span class="badge <?= $donation['status'] === 'Success' ? 'bg-success' : 'bg-danger' ?>">
                                                <?= $donation['status'] ?>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Right Column: Signals, Case Info, Actions -->
            <div class="col-lg-4">
                <!-- Fraud Signals -->
                <div class="card-custom">
                    <h4 class="section-title">
                        <i class="bi bi-exclamation-triangle"></i> Fraud Signals (<?= count($signals) ?>)
                    </h4>
                    
                    <?php if (empty($signals)): ?>
                        <p class="text-muted">No fraud signals detected.</p>
                    <?php else: ?>
                        <div style="max-height: 400px; overflow-y: auto;">
                            <?php foreach ($signals as $signal): ?>
                                <div class="signal-item <?= $signal['signal_source'] ?>">
                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                        <span class="badge <?= $signal['signal_source'] === 'system' ? 'bg-primary' : 'bg-warning' ?>">
                                            <?= ucfirst($signal['signal_source']) ?>
                                        </span>
                                        <span class="badge bg-danger">+<?= $signal['weight'] ?> pts</span>
                                    </div>
                                    <small><strong><?= $signal['signal_type'] ?></strong></small><br>
                                    <small class="text-muted"><?= htmlspecialchars($signal['description']) ?></small><br>
                                    <small class="text-muted">
                                        <i class="bi bi-clock"></i> <?= date('M j, Y g:i A', strtotime($signal['created_at'])) ?>
                                    </small>
                                    <?php if ($signal['created_by_name']): ?>
                                        <br><small class="text-muted">By: <?= htmlspecialchars($signal['created_by_name']) ?></small>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Active Case Info -->
                <?php if ($active_case): ?>
                    <div class="card-custom">
                        <h4 class="section-title">
                            <i class="bi bi-folder-fill"></i> Active Case
                        </h4>
                        <p><strong>Status:</strong> <span class="badge bg-info"><?= strtoupper($active_case['status']) ?></span></p>
                        <p><strong>Opened:</strong> <?= date('M j, Y', strtotime($active_case['created_at'])) ?></p>
                        <p><strong>Opened By:</strong> <?= htmlspecialchars($active_case['opened_by_name']) ?></p>
                        <p class="mb-0"><strong>Summary:</strong><br><?= htmlspecialchars($active_case['summary']) ?></p>
                    </div>
                <?php endif; ?>

                <!-- Case Notes -->
                <?php if (!empty($case_notes)): ?>
                    <div class="card-custom">
                        <h4 class="section-title">
                            <i class="bi bi-journal-text"></i> Case Notes
                        </h4>
                        <div style="max-height: 300px; overflow-y: auto;">
                            <?php foreach ($case_notes as $note): ?>
                                <div class="case-note">
                                    <small class="text-muted">
                                        <i class="bi bi-person"></i> <?= htmlspecialchars($note['admin_name']) ?> • 
                                        <?= date('M j, Y g:i A', strtotime($note['created_at'])) ?>
                                    </small>
                                    <p class="mb-0 mt-1"><?= nl2br(htmlspecialchars($note['note'])) ?></p>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Pending Appeals -->
                <?php if (!empty($appeals)): ?>
                    <div class="card-custom">
                        <h4 class="section-title">
                            <i class="bi bi-megaphone"></i> Appeals
                        </h4>
                        <?php foreach ($appeals as $appeal): ?>
                            <div class="appeal-card">
                                <span class="badge bg-<?= $appeal['status'] === 'pending' ? 'warning' : ($appeal['status'] === 'accepted' ? 'success' : 'danger') ?>">
                                    <?= strtoupper($appeal['status']) ?>
                                </span>
                                <p class="mt-2 mb-1"><strong>Sponsor's Statement:</strong></p>
                                <p class="text-muted"><?= nl2br(htmlspecialchars($appeal['appeal_text'])) ?></p>
                                <small class="text-muted">
                                    <i class="bi bi-clock"></i> Submitted: <?= date('M j, Y', strtotime($appeal['created_at'])) ?>
                                </small>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <!-- Admin Actions -->
                <div class="card-custom">
                    <h4 class="section-title">
                        <i class="bi bi-shield-check"></i> Admin Actions
                    </h4>
                    
                    <form method="POST" onsubmit="return confirm('Are you sure you want to take this action? This will be logged and audited.');">
                        <div class="mb-3">
                            <label class="form-label fw-bold">Justification (Required)</label>
                            <textarea name="justification" class="justification-box" 
                                      placeholder="Explain your decision in detail. This will be permanently recorded in the audit trail..."
                                      required></textarea>
                        </div>
                        
                        <div class="action-buttons">
                            <button type="submit" name="action" value="clear" class="action-btn btn-clear">
                                <i class="bi bi-check-circle"></i><br>Clear
                            </button>
                            <button type="submit" name="action" value="restrict" class="action-btn btn-restrict">
                                <i class="bi bi-exclamation-circle"></i><br>Restrict
                            </button>
                            <button type="submit" name="action" value="freeze" class="action-btn btn-freeze">
                                <i class="bi bi-snow"></i><br>Freeze
                            </button>
                            <button type="submit" name="action" value="block" class="action-btn btn-block">
                                <i class="bi bi-x-circle"></i><br>Block
                            </button>
                        </div>
                        
                        <div class="mt-3">
                            <small class="text-muted">
                                <i class="bi bi-info-circle"></i> 
                                <strong>Clear:</strong> Remove flags, reduce risk score<br>
                                <strong>Restrict:</strong> Donation limits applied<br>
                                <strong>Freeze:</strong> Temporarily suspend all donations<br>
                                <strong>Block:</strong> Permanently ban account
                            </small>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Prepare data for charts
        const donationDates = <?= json_encode($donation_dates) ?>;
        const donationAmounts = <?= json_encode($donation_amounts) ?>;
        const donationStatuses = <?= json_encode($donation_statuses) ?>;
        
        // Chart 1: Donation Amount Over Time
        const ctx1 = document.getElementById('donationChart').getContext('2d');
        new Chart(ctx1, {
            type: 'line',
            data: {
                labels: donationDates,
                datasets: [{
                    label: 'Donation Amount (₹)',
                    data: donationAmounts,
                    borderColor: '#FFB3B3',
                    backgroundColor: 'rgba(255, 179, 179, 0.1)',
                    borderWidth: 3,
                    fill: true,
                    tension: 0.4,
                    pointBackgroundColor: '#FF9999',
                    pointBorderColor: '#fff',
                    pointBorderWidth: 2,
                    pointRadius: 5,
                    pointHoverRadius: 7
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: true,
                        position: 'top'
                    },
                    tooltip: {
                        backgroundColor: '#2C2C2C',
                        padding: 12,
                        cornerRadius: 8
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return '₹' + value.toLocaleString();
                            }
                        }
                    }
                }
            }
        });
        
        // Chart 2: Payment Status Distribution
        const statusCounts = {
            Success: donationStatuses.filter(s => s === 'Success').length,
            Failed: donationStatuses.filter(s => s === 'Failed').length,
            Pending: donationStatuses.filter(s => s === 'Pending').length
        };
        
        const ctx2 = document.getElementById('frequencyChart').getContext('2d');
        new Chart(ctx2, {
            type: 'bar',
            data: {
                labels: ['Success', 'Failed', 'Pending'],
                datasets: [{
                    label: 'Number of Payments',
                    data: [statusCounts.Success, statusCounts.Failed, statusCounts.Pending],
                    backgroundColor: [
                        'rgba(40, 167, 69, 0.8)',
                        'rgba(220, 53, 69, 0.8)',
                        'rgba(255, 193, 7, 0.8)'
                    ],
                    borderColor: [
                        '#28a745',
                        '#dc3545',
                        '#ffc107'
                    ],
                    borderWidth: 2,
                    borderRadius: 8
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1
                        }
                    }
                }
            }
        });
    </script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>