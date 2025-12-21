<?php
// signals_overview.php - Admin read-only view of all fraud signals
session_start();
require_once __DIR__ . '/../db_config.php';
require_once __DIR__ . '/../includes/fraud_services.php';




// Get filters from query params
$filter_type = $_GET['type'] ?? 'all';
$filter_source = $_GET['source'] ?? 'all';
$filter_sponsor = $_GET['sponsor_id'] ?? null;
$sort_by = $_GET['sort'] ?? 'recent';
$search = $_GET['search'] ?? '';

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

if ($search) {
    $query .= " AND (CONCAT(s.first_name, ' ', s.last_name) LIKE ? OR u.email LIKE ? OR fs.description LIKE ?)";
    $search_param = "%{$search}%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= 'sss';
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

// Get initials for avatar
function getInitials($name) {
    $parts = explode(' ', $name);
    return strtoupper(substr($parts[0], 0, 1) . (isset($parts[1]) ? substr($parts[1], 0, 1) : ''));
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fraud Signals - Admin Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
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
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
        }
        
        .main-header {
            background: white;
            border-radius: 20px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 4px 15px rgba(255, 179, 179, 0.1);
        }
        
        .main-header h1 {
            font-weight: 700;
            color: var(--text-dark);
            margin-bottom: 0.5rem;
        }
        
        .main-header p {
            color: #6C757D;
            margin: 0;
        }
        
        .stat-card {
            background: white;
            border-radius: 16px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 2px 10px rgba(255, 179, 179, 0.08);
            border: 1px solid var(--border-light);
            transition: all 0.3s ease;
        }
        
        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 20px rgba(255, 179, 179, 0.15);
        }
        
        .stat-card.highlight {
            background: linear-gradient(135deg, #FFB3B3 0%, #FF9999 100%);
            color: white;
        }
        
        .stat-card.highlight h6 {
            color: rgba(255, 255, 255, 0.9);
        }
        
        .stat-number {
            font-size: 2.5rem;
            font-weight: 700;
            margin: 0.5rem 0;
        }
        
        .search-filter-section {
            background: white;
            border-radius: 16px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            box-shadow: 0 2px 10px rgba(255, 179, 179, 0.08);
        }
        
        .search-box {
            position: relative;
        }
        
        .search-box input {
            border-radius: 50px;
            padding: 0.75rem 1.5rem 0.75rem 3rem;
            border: 2px solid var(--border-light);
            transition: all 0.3s ease;
        }
        
        .search-box input:focus {
            border-color: var(--accent-pink);
            box-shadow: 0 0 0 3px rgba(255, 179, 179, 0.1);
        }
        
        .search-box i {
            position: absolute;
            left: 1.2rem;
            top: 50%;
            transform: translateY(-50%);
            color: #999;
        }
        
        .filter-buttons {
            display: flex;
            gap: 0.75rem;
            flex-wrap: wrap;
        }
        
        .filter-btn {
            padding: 0.5rem 1.5rem;
            border-radius: 50px;
            border: 2px solid var(--border-light);
            background: white;
            color: var(--text-dark);
            font-weight: 500;
            transition: all 0.3s ease;
            cursor: pointer;
        }
        
        .filter-btn:hover {
            background: var(--card-pink);
            border-color: var(--accent-pink);
        }
        
        .filter-btn.active {
            background: var(--accent-pink);
            border-color: var(--accent-pink);
            color: white;
        }
        
        .signal-card {
            background: white;
            border-radius: 16px;
            padding: 1.5rem;
            margin-bottom: 1rem;
            box-shadow: 0 2px 10px rgba(255, 179, 179, 0.08);
            border-left: 5px solid transparent;
            transition: all 0.3s ease;
        }
        
        .signal-card:hover {
            transform: translateX(5px);
            box-shadow: 0 4px 20px rgba(255, 179, 179, 0.15);
        }
        
        .signal-card.system {
            border-left-color: #0d6efd;
        }
        
        .signal-card.staff {
            border-left-color: #ffc107;
        }
        
        .sponsor-avatar {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: linear-gradient(135deg, #FFB3B3 0%, #FF9999 100%);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 1.2rem;
            box-shadow: 0 2px 8px rgba(255, 179, 179, 0.3);
        }
        
        .risk-badge {
            padding: 0.4rem 1rem;
            border-radius: 50px;
            font-weight: 600;
            font-size: 0.85rem;
        }
        
        .weight-indicator {
            background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 12px;
            font-weight: 700;
            display: inline-block;
        }
        
        .review-btn {
            background: linear-gradient(135deg, #0d6efd 0%, #0b5ed7 100%);
            color: white;
            border: none;
            padding: 0.5rem 1.5rem;
            border-radius: 50px;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .review-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(13, 110, 253, 0.3);
            color: white;
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
        
        .signal-type-badge {
            background: var(--card-pink);
            color: var(--text-dark);
            padding: 0.3rem 0.8rem;
            border-radius: 8px;
            font-size: 0.85rem;
            font-weight: 600;
        }
        
        .empty-state {
            background: white;
            border-radius: 16px;
            padding: 3rem;
            text-align: center;
            color: #6C757D;
        }
        
        .empty-state i {
            font-size: 4rem;
            color: var(--accent-pink);
            margin-bottom: 1rem;
        }
        
        .form-select, .form-control {
            border-radius: 12px;
            border: 2px solid var(--border-light);
        }
        
        .form-select:focus, .form-control:focus {
            border-color: var(--accent-pink);
            box-shadow: 0 0 0 3px rgba(255, 179, 179, 0.1);
        }
    </style>
</head>
<body>
    <div class="container-fluid px-4 py-4">
        <a href="admin_dashboard.php" class="back-button">
            <i class="bi bi-arrow-left"></i> Back to Dashboard
        </a>
        
        <div class="main-header">
            <h1><i class="bi bi-shield-exclamation"></i> Fraud Signals</h1>
            <p>Review and manage all automated and staff-reported fraud signals</p>
        </div>

        <!-- Summary Stats -->
        <div class="row">
            <div class="col-md-3">
                <div class="stat-card highlight">
                    <h6>TOTAL SIGNALS</h6>
                    <div class="stat-number"><?= number_format($stats['total_signals']) ?></div>
                    <small>Last 30 days</small>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <h6 class="text-muted">AUTOMATED SIGNALS</h6>
                    <div class="stat-number text-primary"><?= number_format($stats['auto_signals']) ?></div>
                    <small class="text-muted">System detected</small>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <h6 class="text-muted">STAFF REPORTS</h6>
                    <div class="stat-number text-warning"><?= number_format($stats['staff_signals']) ?></div>
                    <small class="text-muted">Manually flagged</small>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <h6 class="text-muted">UNIQUE SPONSORS</h6>
                    <div class="stat-number"><?= number_format($stats['unique_sponsors']) ?></div>
                    <small class="text-muted">Flagged accounts</small>
                </div>
            </div>
        </div>

        <!-- Search and Filters -->
        <div class="search-filter-section">
            <form method="GET" action="">
                <div class="row mb-3">
                    <div class="col-md-12">
                        <div class="search-box">
                            <i class="bi bi-search"></i>
                            <input type="text" name="search" class="form-control" 
                                   placeholder="Search by name, email, or flag reason..." 
                                   value="<?= htmlspecialchars($search) ?>">
                        </div>
                    </div>
                </div>
                
                <div class="row g-3 mb-3">
                    <div class="col-md-3">
                        <label class="form-label fw-bold">Signal Type</label>
                        <select name="type" class="form-select">
                            <option value="all" <?= $filter_type === 'all' ? 'selected' : '' ?>>All Types</option>
                            <option value="auto_frequency" <?= $filter_type === 'auto_frequency' ? 'selected' : '' ?>>High Frequency</option>
                            <option value="auto_amount_spike" <?= $filter_type === 'auto_amount_spike' ? 'selected' : '' ?>>Amount Spike</option>
                            <option value="auto_payment_integrity" <?= $filter_type === 'auto_payment_integrity' ? 'selected' : '' ?>>Payment Issue</option>
                            <option value="staff_report" <?= $filter_type === 'staff_report' ? 'selected' : '' ?>>Staff Report</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-bold">Source</label>
                        <select name="source" class="form-select">
                            <option value="all" <?= $filter_source === 'all' ? 'selected' : '' ?>>All Sources</option>
                            <option value="system" <?= $filter_source === 'system' ? 'selected' : '' ?>>Automated</option>
                            <option value="staff" <?= $filter_source === 'staff' ? 'selected' : '' ?>>Staff</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-bold">Sort By</label>
                        <select name="sort" class="form-select">
                            <option value="recent" <?= $sort_by === 'recent' ? 'selected' : '' ?>>Most Recent</option>
                            <option value="oldest" <?= $sort_by === 'oldest' ? 'selected' : '' ?>>Oldest First</option>
                            <option value="weight_high" <?= $sort_by === 'weight_high' ? 'selected' : '' ?>>Highest Weight</option>
                            <option value="weight_low" <?= $sort_by === 'weight_low' ? 'selected' : '' ?>>Lowest Weight</option>
                        </select>
                    </div>
                    <div class="col-md-3 d-flex align-items-end">
                        <button type="submit" class="btn review-btn w-100">
                            <i class="bi bi-funnel"></i> Apply Filters
                        </button>
                    </div>
                </div>
            </form>
        </div>

        <!-- Signals List -->
        <?php if (empty($signals)): ?>
            <div class="empty-state">
                <i class="bi bi-inbox"></i>
                <h4>No signals found</h4>
                <p class="text-muted">Try adjusting your filters or search criteria</p>
            </div>
        <?php else: ?>
            <?php foreach ($signals as $signal): ?>
                <div class="signal-card <?= $signal['signal_source'] ?>">
                    <div class="row align-items-center">
                        <div class="col-md-auto">
                            <div class="sponsor-avatar">
                                <?= getInitials($signal['sponsor_name']) ?>
                            </div>
                        </div>
                        
                        <div class="col-md-3">
                            <div>
                                <strong style="font-size: 1.1rem;"><?= htmlspecialchars($signal['sponsor_name']) ?></strong>
                                <br>
                                <small class="text-muted">
                                    <i class="bi bi-envelope"></i> <?= htmlspecialchars($signal['sponsor_email']) ?>
                                </small>
                                <br>
                                <span class="risk-badge bg-<?= getRiskBadge($signal['risk_level']) ?>">
                                    <?= ucfirst($signal['risk_level']) ?> • Score: <?= $signal['risk_score'] ?>
                                </span>
                            </div>
                        </div>
                        
                        <div class="col-md-4">
                            <div class="mb-2">
                                <span class="signal-type-badge">
                                    <?= getSignalTypeLabel($signal['signal_type']) ?>
                                </span>
                                <?php if ($signal['signal_source'] === 'system'): ?>
                                    <span class="badge bg-primary">Automated</span>
                                <?php else: ?>
                                    <span class="badge bg-warning text-dark">Staff Report</span>
                                <?php endif; ?>
                            </div>
                            <small class="text-muted d-block">
                                <?= htmlspecialchars($signal['description']) ?>
                            </small>
                            <?php if ($signal['created_by_name']): ?>
                                <small class="text-muted">
                                    <i class="bi bi-person"></i> Reported by: <?= htmlspecialchars($signal['created_by_name']) ?>
                                </small>
                            <?php endif; ?>
                        </div>
                        
                        <div class="col-md-2 text-center">
                            <div class="weight-indicator">
                                +<?= $signal['weight'] ?> pts
                            </div>
                            <small class="text-muted d-block mt-2">
                                <i class="bi bi-clock"></i> <?= date('M j, Y', strtotime($signal['created_at'])) ?>
                            </small>
                        </div>
                        
                        <div class="col-md-2 text-end">
                            <?php if ($signal['case_status'] === 'In Case'): ?>
                                <span class="badge bg-info mb-2">
                                    <i class="bi bi-folder-fill"></i> Active Case
                                </span>
                                <br>
                            <?php endif; ?>
                            <a href="review_case.php?sponsor_id=<?= $signal['sponsor_id'] ?>" 
                               class="review-btn text-decoration-none">
                                <i class="bi bi-eye"></i> Review
                            </a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>