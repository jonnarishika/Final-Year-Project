<?php
// child_management.php
// Staff page to manage children - view all children and add new ones

session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header('Location: ../signup_and_login/login.php');
    exit();
}

// Check role - handle both uppercase and lowercase
$user_role = strtolower($_SESSION['role']); // Convert to lowercase for comparison
if ($user_role !== 'staff' && $user_role !== 'owner') {
    header('Location: ../signup_and_login/login.php');
    exit();
}

// Include database connection
require_once '../db_config.php';

// Handle filters from GET parameters
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$age_range = isset($_GET['age_range']) ? $_GET['age_range'] : '';
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'name_asc';

// Build WHERE clause
$where_clauses = [];
$params = [];
$types = '';

// Search by name
if (!empty($search)) {
    $where_clauses[] = "(LOWER(first_name) LIKE LOWER(?) OR LOWER(last_name) LIKE LOWER(?) OR LOWER(CONCAT(first_name, ' ', last_name)) LIKE LOWER(?))";
    $search_param = '%' . $search . '%';
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= 'sss';
}

// Age range filter
if (!empty($age_range)) {
    $age_parts = explode('-', $age_range);
    if (count($age_parts) === 2) {
        $min_age = intval($age_parts[0]);
        $max_age = intval($age_parts[1]);
        
        $today = new DateTime();
        $max_birth_date = (clone $today)->modify("-" . ($max_age + 1) . " years")->modify("+1 day")->format('Y-m-d');
        $min_birth_date = (clone $today)->modify("-{$min_age} years")->format('Y-m-d');
        
        $where_clauses[] = "(dob >= ? AND dob <= ?)";
        $params[] = $max_birth_date;
        $params[] = $min_birth_date;
        $types .= 'ss';
    }
}

// Build ORDER BY clause
$order_by = "first_name ASC, last_name ASC";
switch ($sort) {
    case 'name_asc':
        $order_by = "first_name ASC, last_name ASC";
        break;
    case 'name_desc':
        $order_by = "first_name DESC, last_name DESC";
        break;
    case 'age_asc':
        $order_by = "dob DESC";
        break;
    case 'age_desc':
        $order_by = "dob ASC";
        break;
    case 'newest':
        $order_by = "created_at DESC";
        break;
}

// Build and execute query
$where_sql = count($where_clauses) > 0 ? implode(' AND ', $where_clauses) : '1=1';
$query = "SELECT child_id, first_name, last_name, dob, gender, profile_picture, status, about_me, aspiration, created_at 
          FROM children 
          WHERE {$where_sql} 
          ORDER BY {$order_by}";

$stmt = $conn->prepare($query);

if (!$stmt) {
    die("Query preparation failed: " . $conn->error);
}

// Bind parameters if any
if (!empty($types)) {
    $bind_names = [$types];
    for ($i = 0; $i < count($params); $i++) {
        $bind_names[] = &$params[$i];
    }
    call_user_func_array([$stmt, 'bind_param'], $bind_names);
}

$stmt->execute();
$result = $stmt->get_result();

$children = [];
while ($row = $result->fetch_assoc()) {
    $children[] = $row;
}

$stmt->close();

// Calculate age helper function
function calculateAge($dob) {
    $birthDate = new DateTime($dob);
    $today = new DateTime();
    $age = $today->diff($birthDate)->y;
    return $age;
}

// Get initials helper function
function getInitials($firstName, $lastName) {
    return strtoupper(substr($firstName, 0, 1) . substr($lastName, 0, 1));
}

// Include sidebar configuration
require_once '../components/sidebar_config.php';
$sidebar_menu = initSidebar('staff', 'child_management.php');
$logout_path = '../signup_and_login/logout.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Child Management - Staff Dashboard</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background: linear-gradient(180deg, 
                #7b9ec4 0%, 
                #8fa9c9 35%, 
                #a3b8d1 70%,
                #fffef8 95%,
                #fffcf5 100%);
            min-height: 100vh;
            padding-top: 80px;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 3rem 2rem;
        }

        /* Hero Section */
        .hero-section {
            background: rgba(255, 255, 255, 0.25);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.4);
            border-radius: 24px;
            padding: 3rem 2.5rem;
            margin-bottom: 3rem;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
        }

        .hero-title {
            font-size: 2.5rem;
            font-weight: 800;
            color: #1a1a1a;
            margin-bottom: 1rem;
            text-align: center;
        }

        .hero-description {
            font-size: 1.1rem;
            color: #666;
            text-align: center;
            max-width: 700px;
            margin: 0 auto;
            line-height: 1.6;
        }

        /* Filters Section */
        .filters-section {
            background: rgba(255, 255, 255, 0.3);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.4);
            border-radius: 20px;
            padding: 2rem;
            margin-bottom: 2.5rem;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
        }

        .filters-form {
            display: grid;
            grid-template-columns: 2fr 1fr 1fr;
            gap: 1.5rem;
            margin-bottom: 1.5rem;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        .filter-label {
            font-weight: 600;
            color: #555;
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .filter-input,
        .filter-select {
            padding: 0.875rem 1.125rem;
            border: 1px solid rgba(255, 255, 255, 0.3);
            border-radius: 12px;
            font-size: 1rem;
            color: #333;
            background: rgba(255, 255, 255, 0.4);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            transition: all 0.3s ease;
        }

        .filter-input:focus,
        .filter-select:focus {
            outline: none;
            border-color: rgba(74, 144, 164, 0.6);
            background: rgba(255, 255, 255, 0.6);
            box-shadow: 0 0 0 3px rgba(74, 144, 164, 0.15);
        }

        .filter-actions {
            display: flex;
            gap: 1rem;
            justify-content: flex-end;
        }

        .btn {
            padding: 0.875rem 2rem;
            border: none;
            border-radius: 12px;
            font-weight: 600;
            font-size: 0.95rem;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
        }

        .btn-reset {
            background: #f0f0f0;
            color: #666;
        }

        .btn-reset:hover {
            background: #e0e0e0;
        }

        .btn-apply {
            background: linear-gradient(135deg, #4A90A4 0%, #5FA4B8 100%);
            color: white;
            box-shadow: 0 4px 12px rgba(74, 144, 164, 0.3);
        }

        .btn-apply:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(74, 144, 164, 0.4);
        }

        /* Results Info */
        .results-info {
            font-size: 1rem;
            color: #666;
            margin-bottom: 2rem;
        }

        /* Children Grid */
        .children-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 2rem;
            margin-bottom: 3rem;
        }

        /* Add Child Card */
        .add-child-card {
            background: rgba(255, 255, 255, 0.25);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 2px dashed rgba(74, 144, 164, 0.5);
            border-radius: 20px;
            padding: 3rem 2rem;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 1.5rem;
            cursor: pointer;
            transition: all 0.3s ease;
            min-height: 400px;
            text-decoration: none;
        }

        .add-child-card:hover {
            transform: translateY(-5px);
            background: rgba(255, 255, 255, 0.35);
            border-color: rgba(74, 144, 164, 0.7);
            box-shadow: 0 12px 40px rgba(74, 144, 164, 0.2);
        }

        .add-icon {
            width: 80px;
            height: 80px;
            background: rgba(74, 144, 164, 0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 3rem;
            color: #4A90A4;
            font-weight: 300;
            transition: all 0.3s ease;
        }

        .add-child-card:hover .add-icon {
            transform: rotate(90deg);
            background: rgba(74, 144, 164, 0.3);
        }

        .add-child-text {
            font-size: 1.25rem;
            font-weight: 700;
            color: #4A90A4;
            text-align: center;
        }

        .add-child-description {
            font-size: 0.95rem;
            color: #666;
            text-align: center;
            max-width: 250px;
        }

        /* Child Card */
        .child-card {
            background: rgba(255, 255, 255, 0.3);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.4);
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
            display: flex;
            flex-direction: column;
        }

        .child-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 12px 40px rgba(0, 0, 0, 0.15);
            background: rgba(255, 255, 255, 0.4);
        }

        .child-image-container {
            width: 100%;
            height: 280px;
            background: rgba(255, 255, 255, 0.2);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            position: relative;
        }

        .child-image {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .child-initials {
            font-size: 4rem;
            font-weight: 700;
            color: rgba(0, 0, 0, 0.15);
            text-transform: uppercase;
        }

        .child-info {
            padding: 1.75rem;
        }

        .child-name {
            font-size: 1.35rem;
            font-weight: 700;
            color: #1a1a1a;
            margin-bottom: 0.75rem;
        }

        .child-details {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
            margin-bottom: 1.5rem;
        }

        .child-detail {
            font-size: 0.95rem;
            color: #666;
        }

        .status-badge {
            display: inline-block;
            padding: 0.4rem 0.9rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            margin-bottom: 1rem;
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.3);
        }

        .status-sponsored {
            background: rgba(34, 197, 94, 0.25);
            color: #15803d;
        }

        .status-unsponsored {
            background: rgba(234, 179, 8, 0.25);
            color: #a16207;
        }

        .view-profile-btn {
            width: 100%;
            padding: 0.875rem;
            background: linear-gradient(135deg, #4A90A4 0%, #5FA4B8 100%);
            color: white;
            border: none;
            border-radius: 12px;
            font-weight: 600;
            font-size: 0.95rem;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: block;
            text-align: center;
        }

        .view-profile-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(74, 144, 164, 0.3);
        }

        .no-children {
            grid-column: 1 / -1;
            text-align: center;
            padding: 4rem 2rem;
            background: rgba(255, 255, 255, 0.3);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.4);
            border-radius: 20px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
        }

        .no-children-icon {
            font-size: 4rem;
            margin-bottom: 1rem;
            opacity: 0.3;
        }

        .no-children-text {
            font-size: 1.25rem;
            color: #666;
            margin-bottom: 0.5rem;
        }

        .no-children-subtext {
            font-size: 0.95rem;
            color: #999;
        }

        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.6);
            backdrop-filter: blur(5px);
            -webkit-backdrop-filter: blur(5px);
            z-index: 10000;
            padding: 2rem;
            overflow-y: auto;
        }

        .modal.active {
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .modal-content {
            background: linear-gradient(180deg, 
                #6b8db3 0%, 
                #7a9ac0 35%, 
                #90a9c9 70%,
                #a8b8d1 100%);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.4);
            border-radius: 24px;
            max-width: 1000px;
            width: 100%;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            position: relative;
            animation: modalSlideIn 0.3s ease;
        }

        @keyframes modalSlideIn {
            from {
                transform: translateY(-50px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        .modal-header {
            position: relative;
            padding: 2rem 2rem 1rem 2rem;
            border-bottom: 1px solid rgba(0, 0, 0, 0.1);
        }

        .modal-close {
            position: absolute;
            top: 1.5rem;
            right: 1.5rem;
            width: 40px;
            height: 40px;
            border: none;
            background: rgba(0, 0, 0, 0.1);
            border-radius: 50%;
            cursor: pointer;
            font-size: 1.5rem;
            color: #666;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
        }

        .modal-close:hover {
            background: rgba(0, 0, 0, 0.15);
            color: #333;
        }

        .modal-edit-btn {
            position: absolute;
            top: 1.5rem;
            right: 1.5rem;
            padding: 0.75rem 1.5rem;
            background: rgba(255, 255, 255, 0.25);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            color: white;
            border: 1px solid rgba(255, 255, 255, 0.3);
            border-radius: 12px;
            font-weight: 600;
            font-size: 0.9rem;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .modal-edit-btn:hover {
            transform: translateY(-2px);
            background: rgba(255, 255, 255, 0.35);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
        }

        .modal-body {
            padding: 2rem;
            display: flex;
            gap: 2rem;
        }

        .modal-left {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 1rem;
        }

        .modal-image-container {
            width: 150px;
            height: 150px;
            background: rgba(255, 255, 255, 0.3);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
        }

        .modal-image {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .modal-initials {
            font-size: 3rem;
            font-weight: 700;
            color: rgba(255, 255, 255, 0.4);
            text-transform: uppercase;
        }

        .modal-name {
            font-size: 1.35rem;
            font-weight: 800;
            color: white;
            text-align: center;
            margin-bottom: 0.5rem;
        }

        .modal-right {
            flex: 1;
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
        }

        .modal-section {
            background: rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 16px;
            padding: 1.5rem;
        }

        .modal-section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }

        .modal-section-title {
            font-size: 1.25rem;
            font-weight: 700;
            color: white;
        }

        .modal-edit-icon {
            width: 36px;
            height: 36px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .modal-edit-icon:hover {
            background: rgba(255, 255, 255, 0.3);
        }

        .modal-section-content {
            color: rgba(255, 255, 255, 0.9);
            font-size: 1rem;
            line-height: 1.6;
        }

        .modal-status-badge {
            padding: 0.5rem 1.2rem;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.3);
            display: inline-block;
        }

        .modal-name {
            font-size: 1.5rem;
            font-weight: 800;
            color: white;
            text-align: center;
            margin-bottom: 0.5rem;
        }

        .modal-details {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1rem;
        }

        .modal-detail-item {
            background: rgba(255, 255, 255, 0.1);
            border-radius: 12px;
            padding: 1rem;
        }

        .modal-detail-label {
            font-size: 0.75rem;
            font-weight: 600;
            color: rgba(255, 255, 255, 0.7);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 0.5rem;
        }

        .modal-detail-value {
            font-size: 1rem;
            color: white;
            font-weight: 600;
        }

        /* Responsive */
        @media (max-width: 968px) {
            .filters-form {
                grid-template-columns: 1fr;
            }

            .children-grid {
                grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
                gap: 1.5rem;
            }

            .hero-title {
                font-size: 2rem;
            }
        }

        @media (max-width: 640px) {
            .container {
                padding: 2rem 1rem;
            }

            .hero-section {
                padding: 2rem 1.5rem;
            }

            .filters-section {
                padding: 1.5rem;
            }

            .children-grid {
                grid-template-columns: 1fr;
            }

            .modal-content {
                margin: 1rem;
            }

            .modal-body {
                flex-direction: column;
            }

            .modal-left {
                width: 100%;
            }

            .modal-details {
                grid-template-columns: 1fr;
            }

            .modal-name {
                font-size: 1.5rem;
            }

            .modal-edit-btn {
                position: static;
                margin-bottom: 1rem;
                display: block;
                text-align: center;
            }
        }
    </style>
</head>
<body>
    <?php 
    require_once '../components/header.php';
    require_once '../components/sidebar.php';
    ?>

    <div class="container">
        <!-- Hero Section -->
        <div class="hero-section">
            <h1 class="hero-title">Child Management</h1>
            <p class="hero-description">
                View, manage, and add children to the sponsorship program. Keep track of all children and their sponsorship status.
            </p>
        </div>

        <!-- Filters Section -->
        <div class="filters-section">
            <form method="GET" action="child_management.php" class="filters-form" id="filters-form">
                <div class="filter-group">
                    <label class="filter-label">Search by Name</label>
                    <input 
                        type="text" 
                        name="search"
                        class="filter-input" 
                        placeholder="Enter child's name..."
                        value="<?php echo htmlspecialchars($search); ?>"
                    >
                </div>
                
                <div class="filter-group">
                    <label class="filter-label">Age Range</label>
                    <select name="age_range" class="filter-select">
                        <option value="">All Ages</option>
                        <option value="0-5" <?php echo $age_range === '0-5' ? 'selected' : ''; ?>>0-5 years</option>
                        <option value="6-10" <?php echo $age_range === '6-10' ? 'selected' : ''; ?>>6-10 years</option>
                        <option value="11-15" <?php echo $age_range === '11-15' ? 'selected' : ''; ?>>11-15 years</option>
                        <option value="16-18" <?php echo $age_range === '16-18' ? 'selected' : ''; ?>>16-18 years</option>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label class="filter-label">Sort By</label>
                    <select name="sort" class="filter-select">
                        <option value="name_asc" <?php echo $sort === 'name_asc' ? 'selected' : ''; ?>>Name (A-Z)</option>
                        <option value="name_desc" <?php echo $sort === 'name_desc' ? 'selected' : ''; ?>>Name (Z-A)</option>
                        <option value="age_asc" <?php echo $sort === 'age_asc' ? 'selected' : ''; ?>>Age (Youngest First)</option>
                        <option value="age_desc" <?php echo $sort === 'age_desc' ? 'selected' : ''; ?>>Age (Oldest First)</option>
                        <option value="newest" <?php echo $sort === 'newest' ? 'selected' : ''; ?>>Recently Added</option>
                    </select>
                </div>
            </form>
            
            <div class="filter-actions">
                <a href="child_management.php" class="btn btn-reset">Reset</a>
                <button type="submit" form="filters-form" class="btn btn-apply">Apply Filters</button>
            </div>
        </div>

        <!-- Results Info -->
        <div class="results-info">
            Showing <?php echo count($children); ?> children
        </div>

        <!-- Children Grid -->
        <div class="children-grid">
            <!-- Add Child Card (Always First) -->
            <a href="add_child.php" class="add-child-card">
                <div class="add-icon">+</div>
                <div class="add-child-text">Add New Child</div>
                <p class="add-child-description">
                    Click here to register a new child in the sponsorship program
                </p>
            </a>
            
            <!-- Children Cards -->
            <?php if (count($children) > 0): ?>
                <?php foreach ($children as $child): ?>
                    <?php
                    $age = calculateAge($child['dob']);
                    $initials = getInitials($child['first_name'], $child['last_name']);
                    $status = $child['status'] ?? 'Unsponsored';
                    $statusClass = $status === 'Sponsored' ? 'status-sponsored' : 'status-unsponsored';
                    ?>
                    <div class="child-card">
                        <div class="child-image-container">
                            <?php if (!empty($child['profile_picture'])): ?>
                                <img src="../<?php echo htmlspecialchars($child['profile_picture']); ?>" 
                                     alt="<?php echo htmlspecialchars($child['first_name']); ?>" 
                                     class="child-image">
                            <?php else: ?>
                                <div class="child-initials"><?php echo $initials; ?></div>
                            <?php endif; ?>
                        </div>
                        <div class="child-info">
                            <div class="status-badge <?php echo $statusClass; ?>">
                                <?php echo htmlspecialchars($status); ?>
                            </div>
                            <h3 class="child-name">
                                <?php echo htmlspecialchars($child['first_name'] . ' ' . $child['last_name']); ?>
                            </h3>
                            <div class="child-details">
                                <div class="child-detail"><?php echo $age; ?> years old</div>
                                <div class="child-detail"><?php echo htmlspecialchars($child['gender']); ?></div>
                            </div>
                            <button class="view-profile-btn" onclick="openModal(<?php echo htmlspecialchars(json_encode($child)); ?>)">
                                View Profile →
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="no-children">
                    <div class="no-children-icon">👶</div>
                    <div class="no-children-text">No children found</div>
                    <div class="no-children-subtext">Try adjusting your filters or add a new child</div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Modal -->
    <div id="childModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <a href="#" id="modalEditBtn" class="modal-edit-btn">
                    <span>✏️</span>
                    <span>Edit Profile</span>
                </a>
                <button class="modal-close" onclick="closeModal()">×</button>
            </div>
            <div class="modal-body">
                <div class="modal-left">
                    <div class="modal-image-container" id="modalImageContainer"></div>
                    <div id="modalStatusBadge"></div>
                    <h2 class="modal-name" id="modalName"></h2>
                    <div class="modal-details">
                        <div class="modal-detail-item">
                            <div class="modal-detail-label">Age</div>
                            <div class="modal-detail-value" id="modalAge"></div>
                        </div>
                        <div class="modal-detail-item">
                            <div class="modal-detail-label">Gender</div>
                            <div class="modal-detail-value" id="modalGender"></div>
                        </div>
                        <div class="modal-detail-item">
                            <div class="modal-detail-label">Date of Birth</div>
                            <div class="modal-detail-value" id="modalDOB"></div>
                        </div>
                        <div class="modal-detail-item">
                            <div class="modal-detail-label">Status</div>
                            <div class="modal-detail-value" id="modalStatus"></div>
                        </div>
                    </div>
                </div>
                <div class="modal-right">
                    <div class="modal-section">
                        <div class="modal-section-header">
                            <h3 class="modal-section-title">About Me</h3>
                        </div>
                        <div class="modal-section-content" id="modalAboutMe">
                            No information available
                        </div>
                    </div>
                    <div class="modal-section">
                        <div class="modal-section-header">
                            <h3 class="modal-section-title">Dreams & Aspirations</h3>
                        </div>
                        <div class="modal-section-content" id="modalDreams">
                            No information available
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Open modal function
        function openModal(child) {
            const modal = document.getElementById('childModal');
            const imageContainer = document.getElementById('modalImageContainer');
            const statusBadge = document.getElementById('modalStatusBadge');
            const modalName = document.getElementById('modalName');
            const modalAge = document.getElementById('modalAge');
            const modalGender = document.getElementById('modalGender');
            const modalDOB = document.getElementById('modalDOB');
            const modalStatus = document.getElementById('modalStatus');
            const modalAboutMe = document.getElementById('modalAboutMe');
            const modalDreams = document.getElementById('modalDreams');
            const modalEditBtn = document.getElementById('modalEditBtn');
            
            // Calculate age
            const birthDate = new Date(child.dob);
            const today = new Date();
            const age = today.getFullYear() - birthDate.getFullYear();
            const monthDiff = today.getMonth() - birthDate.getMonth();
            const calculatedAge = (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birthDate.getDate())) 
                ? age - 1 
                : age;
            
            // Format date of birth
            const formattedDOB = new Date(child.dob).toLocaleDateString('en-US', {
                year: 'numeric',
                month: 'long',
                day: 'numeric'
            });
            
            // Set image or initials
            if (child.profile_picture) {
                imageContainer.innerHTML = `<img src="../${child.profile_picture}" alt="${child.first_name}" class="modal-image">`;
            } else {
                const initials = child.first_name.charAt(0).toUpperCase() + child.last_name.charAt(0).toUpperCase();
                imageContainer.innerHTML = `<div class="modal-initials">${initials}</div>`;
            }
            
            // Set status badge
            const status = child.status || 'Unsponsored';
            const statusClass = status === 'Sponsored' ? 'status-sponsored' : 'status-unsponsored';
            statusBadge.innerHTML = `<span class="modal-status-badge ${statusClass}">${status}</span>`;
            
            // Set modal content
            modalName.textContent = `${child.first_name} ${child.last_name}`;
            modalAge.textContent = `${calculatedAge} years old`;
            modalGender.textContent = child.gender;
            modalDOB.textContent = formattedDOB;
            modalStatus.textContent = status;
            
            // Set about me and dreams (if available in your database)
            modalAboutMe.textContent = child.about_me || 'No information available';
            modalDreams.textContent = child.aspiration || 'No information available';
            
            // Set edit button link
            modalEditBtn.href = `child_edit.php?id=${child.child_id}`;
            
            // Show modal
            modal.classList.add('active');
            document.body.style.overflow = 'hidden';
        }
        
        // Close modal function
        function closeModal() {
            const modal = document.getElementById('childModal');
            modal.classList.remove('active');
            document.body.style.overflow = 'auto';
        }
        
        // Close modal when clicking outside
        document.getElementById('childModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeModal();
            }
        });
        
        // Close modal with Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeModal();
            }
        });
    </script>
</body>
</html>