<?php
// signals_overview.php - Admin read-only view of all fraud signals
session_start();
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/fraud_service.php';

// Auth check - Admin only
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'Admin') {
    header('Location: ../login.php');
    exit();
}

// Get filters from query params
$filter_type = $_GET['type'] ?? 'all';
$filter_source = $_GET['source'] ?? 'all';
$filter_sponsor = $_GET['sponsor_id'] ?? null;
$sort_by = $_GET['sort'] ?? 'recent';

// Build query with filters
$query = "
    SELECT 
        fs.signal_id,
        fs.sponsor_id,
        fs.signal_type,
        fs.signal_source,
        fs.weight,
        fs.description,
        fs.created_at,
        fs.donation_id,
        CONCAT(s.first_name, ' ', s.last_name) as sponsor_name,
        u.email as sponsor_email,
        CONCAT(creator.first_name, ' ', creator.last_name) as created_by_name,
        srs.risk_score,
        srs.risk_level,
        CASE 
            WHEN fc.fraud_case_id IS NOT NULL THEN 'In Case'
            ELSE 'Open Signal'
        END as case_status
    FROM fraud_signals fs
    INNER JOIN sponsors s ON fs.sponsor_id = s.sponsor_id
    INNER JOIN users u ON s.user_id = u.user_id
    LEFT JOIN users creator ON fs.created_by = creator.user_id
    LEFT JOIN sponsor_risk_scores srs ON fs.sponsor_id = srs.sponsor_id
    LEFT JOIN fraud_cases fc ON fs.sponsor_id = fc.sponsor_id 
        AND fc.status NOT IN ('cleared', 'blocked')
    WHERE 1=1
";

$params = [];
$types = '';

if ($filter_type !== 'all') {
    $query .= " AND fs.signal_type = ?";
    $params[] = $filter_type;
    $types .= 's';
}

if ($filter_source !== 'all') {
    $query .= " AND fs.signal_source = ?";
    $params[] = $filter_source;
    $types .= 's';
}

if ($filter_sponsor) {
    $query .= " AND fs.sponsor_id = ?";
    $params[] = $filter_sponsor;
    $types .= 'i';
}

// Sorting
switch ($sort_by) {
    case 'weight_high':
        $query .= " ORDER BY fs.weight DESC, fs.created_at DESC";
        break;
    case 'weight_low':
        $query .= " ORDER BY fs.weight ASC, fs.created_at DESC";
        break;
    case 'oldest':
        $query .= " ORDER BY fs.created_at ASC";
        break;
    default: // recent
        $query .= " ORDER BY fs.created_at DESC";
}

$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
$signals = $result->fetch_all(MYSQLI_ASSOC);

// Get summary stats
$stats_query = "
    SELECT 
        COUNT(*) as total_signals,
        SUM(CASE WHEN signal_source = 'system' THEN 1 ELSE 0 END) as auto_signals,
        SUM(CASE WHEN signal_source = 'staff' THEN 1 ELSE 0 END) as staff_signals,
        COUNT(DISTINCT sponsor_id) as unique_sponsors,
        AVG(weight) as avg_weight
    FROM fraud_signals
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
";
$stats_result = $conn->query($stats_query);
$stats = $stats_result->fetch_assoc();

// Helper function for risk level badge
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

// Helper function for signal type label
function getSignalTypeLabel($type) {
    $labels = [
        'auto_frequency' => 'High Frequency',
        'auto_amount_spike' => 'Amount Spike',
        'auto_payment_integrity' => 'Payment Issue',
        'staff_report' => 'Staff Report'
    ];
    return $labels[$type] ?? $type;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fraud Signals Overview - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        .signal-card {
            transition: all 0.2s;
            border-left: 4px solid transparent;
        }
        .signal-card:hover {
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        .signal-card.system {
            border-left-color: #0d6efd;
        }
        .signal-card.staff {
            border-left-color: #ffc107;
        }
        .weight-badge {
            font-weight: 600;
            font-size: 0.9rem;
        }
        .stat-card {
            border-left: 4px solid #0d6efd;
        }
        .filter-section {
            background: #f8f9fa;
            padding: 1.5rem;
            border-radius: 8px;
            margin-bottom: 2rem;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="admin_dashboard.php">
                <i class="bi bi-shield-exclamation"></i> Fraud Detection Admin
            </a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="admin_dashboard.php">Dashboard</a>
                <a class="nav-link active" href="signals_overview.php">Signals</a>
                <a class="nav-link" href="appeals.php">Appeals</a>
                <a class="nav-link" href="../logout.php">Logout</a>
            </div>
        </div>
    </nav>

    <div class="container-fluid mt-4">
        <div class="row mb-4">
            <div class="col">
                <h2><i class="bi bi-radar"></i> Fraud Signals Overview</h2>
                <p class="text-muted">Read-only view of all automated and staff-reported signals</p>
            </div>
        </div>

        <!-- Summary Stats -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card stat-card">
                    <div class="card-body">
                        <h6 class="text-muted">Total Signals (30d)</h6>
                        <h3 class="mb-0"><?= number_format($stats['total_signals']) ?></h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card">
                    <div class="card-body">
                        <h6 class="text-muted">Automated Signals</h6>
                        <h3 class="mb-0 text-primary"><?= number_format($stats['auto_signals']) ?></h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card">
                    <div class="card-body">
                        <h6 class="text-muted">Staff Reports</h6>
                        <h3 class="mb-0 text-warning"><?= number_format($stats['staff_signals']) ?></h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card">
                    <div class="card-body">
                        <h6 class="text-muted">Unique Sponsors</h6>
                        <h3 class="mb-0"><?= number_format($stats['unique_sponsors']) ?></h3>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="filter-section">
            <form method="GET" action="" class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Signal Type</label>
                    <select name="type" class="form-select">
                        <option value="all" <?= $filter_type === 'all' ? 'selected' : '' ?>>All Types</option>
                        <option value="auto_frequency" <?= $filter_type === 'auto_frequency' ? 'selected' : '' ?>>High Frequency</option>
                        <option value="auto_amount_spike" <?= $filter_type === 'auto_amount_spike' ? 'selected' : '' ?>>Amount Spike</option>
                        <option value="auto_payment_integrity" <?= $filter_type === 'auto_payment_integrity' ? 'selected' : '' ?>>Payment Issue</option>
                        <option value="staff_report" <?= $filter_type === 'staff_report' ? 'selected' : '' ?>>Staff Report</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Source</label>
                    <select name="source" class="form-select">
                        <option value="all" <?= $filter_source === 'all' ? 'selected' : '' ?>>All Sources</option>
                        <option value="system" <?= $filter_source === 'system' ? 'selected' : '' ?>>Automated</option>
                        <option value="staff" <?= $filter_source === 'staff' ? 'selected' : '' ?>>Staff</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Sort By</label>
                    <select name="sort" class="form-select">
                        <option value="recent" <?= $sort_by === 'recent' ? 'selected' : '' ?>>Most Recent</option>
                        <option value="oldest" <?= $sort_by === 'oldest' ? 'selected' : '' ?>>Oldest First</option>
                        <option value="weight_high" <?= $sort_by === 'weight_high' ? 'selected' : '' ?>>Highest Weight</option>
                        <option value="weight_low" <?= $sort_by === 'weight_low' ? 'selected' : '' ?>>Lowest Weight</option>
                    </select>
                </div>
                <div class="col-md-3 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="bi bi-funnel"></i> Apply Filters
                    </button>
                </div>
            </form>
        </div>

        <!-- Signals List -->
        <?php if (empty($signals)): ?>
            <div class="alert alert-info">
                <i class="bi bi-info-circle"></i> No signals found matching your filters.
            </div>
        <?php else: ?>
            <div class="row">
                <?php foreach ($signals as $signal): ?>
                    <div class="col-12 mb-3">
                        <div class="card signal-card <?= $signal['signal_source'] ?>">
                            <div class="card-body">
                                <div class="row align-items-center">
                                    <div class="col-md-2">
                                        <div class="d-flex align-items-center">
                                            <?php if ($signal['signal_source'] === 'system'): ?>
                                                <i class="bi bi-cpu text-primary fs-3 me-2"></i>
                                            <?php else: ?>
                                                <i class="bi bi-person-badge text-warning fs-3 me-2"></i>
                                            <?php endif; ?>
                                            <div>
                                                <small class="text-muted d-block">Signal #<?= $signal['signal_id'] ?></small>
                                                <span class="badge bg-<?= $signal['signal_source'] === 'system' ? 'primary' : 'warning' ?>">
                                                    <?= ucfirst($signal['signal_source']) ?>
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-3">
                                        <strong><?= htmlspecialchars($signal['sponsor_name']) ?></strong><br>
                                        <small class="text-muted"><?= htmlspecialchars($signal['sponsor_email']) ?></small><br>
                                        <span class="badge bg-<?= getRiskBadge($signal['risk_level']) ?>">
                                            <?= ucfirst($signal['risk_level']) ?> - Score: <?= $signal['risk_score'] ?>
                                        </span>
                                    </div>
                                    
                                    <div class="col-md-3">
                                        <span class="badge bg-secondary mb-1"><?= getSignalTypeLabel($signal['signal_type']) ?></span><br>
                                        <small class="text-muted"><?= htmlspecialchars($signal['description']) ?></small>
                                        <?php if ($signal['created_by_name']): ?>
                                            <br><small class="text-muted">By: <?= htmlspecialchars($signal['created_by_name']) ?></small>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="col-md-2 text-center">
                                        <div class="weight-badge">
                                            Weight: <span class="text-danger">+<?= $signal['weight'] ?></span>
                                        </div>
                                        <small class="text-muted d-block"><?= date('M j, Y g:i A', strtotime($signal['created_at'])) ?></small>
                                    </div>
                                    
                                    <div class="col-md-2 text-end">
                                        <?php if ($signal['case_status'] === 'In Case'): ?>
                                            <span class="badge bg-info mb-2">In Active Case</span><br>
                                        <?php endif; ?>
                                        <a href="review_case.php?sponsor_id=<?= $signal['sponsor_id'] ?>" 
                                           class="btn btn-sm btn-outline-primary">
                                            <i class="bi bi-eye"></i> Review Sponsor
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>