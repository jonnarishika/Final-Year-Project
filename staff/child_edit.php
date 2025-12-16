<?php
// child_edit.php
// Staff page to edit child information
// MODIFIED: Added Reports section and quick action buttons

session_start();

// Check authentication
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header('Location: ../signup_and_login/login.php');
    exit();
}

// Check role
$user_role = strtolower($_SESSION['role']);
if ($user_role !== 'staff' && $user_role !== 'owner') {
    header('Location: ../signup_and_login/login.php');
    exit();
}

// Include database connection
require_once '../db_config.php';

// Get child ID
$child_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($child_id <= 0) {
    header('Location: child_management.php');
    exit();
}

// Fetch child data
$query = "SELECT * FROM children WHERE child_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param('i', $child_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header('Location: child_management.php');
    exit();
}

$child = $result->fetch_assoc();
$stmt->close();

// Fetch documents for this child
$doc_query = "SELECT * FROM child_uploads WHERE child_id = ? ORDER BY upload_date DESC";
$doc_stmt = $conn->prepare($doc_query);
$doc_stmt->bind_param('i', $child_id);
$doc_stmt->execute();
$doc_result = $doc_stmt->get_result();
$documents = [];
while ($doc = $doc_result->fetch_assoc()) {
    $documents[] = $doc;
}
$doc_stmt->close();

// Fetch reports for this child
$report_query = "SELECT r.*, u.username as uploaded_by_name 
                FROM child_reports r
                LEFT JOIN users u ON r.uploaded_by = u.user_id
                WHERE r.child_id = ? 
                ORDER BY r.report_date DESC";
$report_stmt = $conn->prepare($report_query);
$report_stmt->bind_param('i', $child_id);
$report_stmt->execute();
$report_result = $report_stmt->get_result();
$reports = [];
while ($report = $report_result->fetch_assoc()) {
    $reports[] = $report;
}
$report_stmt->close();

// Helper function to calculate age
function calculateAge($dob) {
    $birthDate = new DateTime($dob);
    $today = new DateTime();
    return $today->diff($birthDate)->y;
}

// Helper function to get initials
function getInitials($firstName, $lastName) {
    return strtoupper(substr($firstName, 0, 1) . substr($lastName, 0, 1));
}

$age = calculateAge($child['dob']);
$initials = getInitials($child['first_name'], $child['last_name']);

// Include sidebar configuration
require_once '../components/sidebar_config.php';
$sidebar_menu = initSidebar('staff', 'child_management.php');
$logout_path = '../signup_and_login/logout.php';

// Check for success message
$success_message = isset($_SESSION['success_message']) ? $_SESSION['success_message'] : '';
unset($_SESSION['success_message']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Child - <?php echo htmlspecialchars($child['first_name'] . ' ' . $child['last_name']); ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 25%, #7e8ba3 50%, #89a6c7 75%, #a8c5e7 100%);
            min-height: 100vh;
            padding: 140px 20px 40px;
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
            background: radial-gradient(circle at 20% 50%, rgba(62, 106, 188, 0.3) 0%, transparent 50%),
                        radial-gradient(circle at 80% 80%, rgba(105, 155, 224, 0.3) 0%, transparent 50%),
                        radial-gradient(circle at 40% 20%, rgba(168, 197, 231, 0.2) 0%, transparent 50%);
            animation: float 20s ease-in-out infinite;
            z-index: 0;
        }

        @keyframes float {
            0%, 100% { transform: translate(0, 0) rotate(0deg); }
            33% { transform: translate(30px, -30px) rotate(120deg); }
            66% { transform: translate(-20px, 20px) rotate(240deg); }
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
            position: relative;
            z-index: 1;
        }

        /* Success Alert */
        .success-alert {
            background: rgba(34, 197, 94, 0.2);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(34, 197, 94, 0.3);
            border-radius: 12px;
            padding: 16px 20px;
            color: #dcfce7;
            font-weight: 600;
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        /* Back Button */
        .back-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 12px 20px;
            background: rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 12px;
            color: white;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s ease;
            margin-bottom: 24px;
        }

        .back-btn:hover {
            background: rgba(255, 255, 255, 0.25);
            transform: translateX(-4px);
        }

        /* Glass Card */
        .glass-card {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border-radius: 24px;
            border: 1px solid rgba(255, 255, 255, 0.2);
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }

        /* Header Section */
        .page-header {
            background: rgba(255, 255, 255, 0.05);
            padding: 40px;
            text-align: center;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .page-header h1 {
            font-size: 2.5rem;
            font-weight: 700;
            color: white;
            margin-bottom: 8px;
        }

        .page-header p {
            color: rgba(255, 255, 255, 0.9);
            font-size: 1.1rem;
        }

        /* Quick Actions Bar */
        .quick-actions {
            background: rgba(255, 255, 255, 0.08);
            backdrop-filter: blur(10px);
            padding: 20px 40px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
        }

        .action-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 12px 20px;
            background: rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 12px;
            color: white;
            text-decoration: none;
            font-weight: 600;
            font-size: 0.9rem;
            transition: all 0.3s ease;
        }

        .action-btn:hover {
            background: rgba(255, 255, 255, 0.25);
            transform: translateY(-2px);
        }

        .action-btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: 1px solid rgba(255, 255, 255, 0.3);
        }

        .action-btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
        }

        /* Main Grid Layout */
        .main-grid {
            display: grid;
            grid-template-columns: 380px 1fr;
            gap: 0;
        }

        /* Profile Sidebar */
        .profile-sidebar {
            background: rgba(255, 255, 255, 0.05);
            padding: 40px;
            border-right: 1px solid rgba(255, 255, 255, 0.1);
        }

        .profile-image-wrapper {
            position: relative;
            width: 180px;
            height: 180px;
            margin: 0 auto 24px;
        }

        .profile-image-container {
            width: 100%;
            height: 100%;
            border-radius: 20px;
            background: rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(10px);
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            border: 2px solid rgba(255, 255, 255, 0.3);
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.15);
        }

        .profile-image {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .profile-initials {
            font-size: 4rem;
            font-weight: 700;
            color: white;
        }

        .edit-image-overlay {
            position: absolute;
            bottom: 8px;
            right: 8px;
            width: 44px;
            height: 44px;
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(10px);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
            transition: all 0.3s ease;
        }

        .edit-image-overlay:hover {
            background: white;
            transform: scale(1.1);
        }

        .profile-name {
            text-align: center;
            margin-bottom: 24px;
        }

        .profile-name h2 {
            font-size: 1.75rem;
            font-weight: 700;
            color: white;
            margin-bottom: 12px;
        }

        .edit-name-btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 16px;
            background: rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 10px;
            color: white;
            font-size: 0.9rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .edit-name-btn:hover {
            background: rgba(255, 255, 255, 0.25);
        }

        .info-group {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 16px;
            padding: 20px;
            margin-bottom: 16px;
        }

        .info-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .info-row:last-child {
            border-bottom: none;
            padding-bottom: 0;
        }

        .info-row:first-child {
            padding-top: 0;
        }

        .info-label {
            font-size: 0.9rem;
            color: rgba(255, 255, 255, 0.7);
            font-weight: 500;
        }

        .info-value {
            display: flex;
            align-items: center;
            gap: 12px;
            font-weight: 600;
            color: white;
        }

        .icon-btn {
            width: 32px;
            height: 32px;
            background: rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 14px;
        }

        .icon-btn:hover {
            background: rgba(255, 255, 255, 0.25);
            transform: scale(1.05);
        }

        /* Content Area */
        .content-area {
            padding: 40px;
        }

        .section-card {
            background: rgba(255, 255, 255, 0.08);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.15);
            border-radius: 20px;
            padding: 32px;
            margin-bottom: 24px;
        }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 16px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.15);
        }

        .section-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: white;
        }

        .section-content {
            color: rgba(255, 255, 255, 0.9);
            line-height: 1.7;
            font-size: 1rem;
        }

        .empty-state {
            text-align: center;
            padding: 40px;
            color: rgba(255, 255, 255, 0.6);
            font-style: italic;
        }

        /* Reports List */
        .reports-list {
            display: flex;
            flex-direction: column;
            gap: 16px;
        }

        .report-item {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 16px;
            padding: 20px;
            transition: all 0.3s ease;
        }

        .report-item:hover {
            background: rgba(255, 255, 255, 0.15);
            transform: translateX(4px);
        }

        .report-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 12px;
        }

        .report-date {
            font-size: 0.85rem;
            color: rgba(255, 255, 255, 0.7);
            font-weight: 600;
        }

        .report-preview {
            color: rgba(255, 255, 255, 0.8);
            font-size: 0.95rem;
            line-height: 1.6;
            margin-bottom: 12px;
        }

        .report-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 0.85rem;
            color: rgba(255, 255, 255, 0.6);
        }

        .view-report-btn {
            padding: 8px 16px;
            background: rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 8px;
            color: white;
            font-weight: 600;
            font-size: 0.875rem;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .view-report-btn:hover {
            background: rgba(255, 255, 255, 0.25);
        }

        /* Documents Grid */
        .documents-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 20px;
        }

        .doc-card {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 16px;
            padding: 24px;
            transition: all 0.3s ease;
        }

        .doc-card:hover {
            background: rgba(255, 255, 255, 0.15);
            transform: translateY(-4px);
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.15);
        }

        .doc-category {
            display: inline-block;
            padding: 6px 14px;
            background: rgba(255, 255, 255, 0.2);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            color: white;
            margin-bottom: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .doc-title {
            font-weight: 600;
            color: white;
            margin-bottom: 8px;
            font-size: 1.1rem;
        }

        .doc-date {
            font-size: 0.9rem;
            color: rgba(255, 255, 255, 0.7);
            margin-bottom: 16px;
        }

        .doc-actions {
            display: flex;
            gap: 8px;
        }

        .doc-btn {
            flex: 1;
            padding: 10px;
            background: rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.3);
            border-radius: 10px;
            color: white;
            font-weight: 600;
            font-size: 0.875rem;
            text-decoration: none;
            text-align: center;
            transition: all 0.3s ease;
            display: block;
        }

        .doc-btn:hover {
            background: rgba(255, 255, 255, 0.25);
            transform: translateY(-2px);
        }

        .upload-card {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border: 2px dashed rgba(255, 255, 255, 0.4);
            border-radius: 16px;
            padding: 40px 20px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 12px;
            cursor: pointer;
            transition: all 0.3s ease;
            min-height: 220px;
        }

        .upload-card:hover {
            background: rgba(255, 255, 255, 0.15);
            border-color: rgba(255, 255, 255, 0.6);
            transform: translateY(-4px);
        }

        .upload-icon {
            width: 60px;
            height: 60px;
            background: rgba(255, 255, 255, 0.2);
            backdrop-filter: blur(10px);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 32px;
            color: white;
            border: 1px solid rgba(255, 255, 255, 0.3);
        }

        .upload-text {
            color: white;
            font-weight: 600;
            font-size: 1.1rem;
        }

        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.7);
            backdrop-filter: blur(8px);
            z-index: 10000;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .modal.active {
            display: flex;
        }

        .modal-content {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 20px;
            padding: 32px;
            max-width: 600px;
            width: 100%;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            animation: modalSlideIn 0.3s ease;
        }

        @keyframes modalSlideIn {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .modal-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: #1a202c;
            margin-bottom: 24px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-label {
            display: block;
            font-weight: 600;
            color: #2d3748;
            margin-bottom: 8px;
            font-size: 0.95rem;
        }

        .form-input,
        .form-select,
        .form-textarea {
            width: 100%;
            padding: 14px 18px;
            border: none;
            border-radius: 12px;
            font-size: 1rem;
            font-family: 'Inter', sans-serif;
            transition: all 0.2s ease;
            background: rgba(255, 255, 255, 0.5);
            backdrop-filter: blur(10px);
            color: #2d3748;
        }

        .form-input::placeholder,
        .form-textarea::placeholder {
            color: rgba(45, 55, 72, 0.5);
        }

        .form-input:focus,
        .form-select:focus,
        .form-textarea:focus {
            outline: none;
            background: rgba(255, 255, 255, 0.7);
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.2);
        }

        .form-textarea {
            resize: vertical;
            min-height: 120px;
            line-height: 1.6;
        }

        .modal-actions {
            display: flex;
            gap: 12px;
            margin-top: 24px;
        }

        .modal-btn {
            flex: 1;
            padding: 14px;
            border: none;
            border-radius: 12px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            font-family: 'Inter', sans-serif;
            font-size: 1rem;
        }

        .modal-btn-cancel {
            background: rgba(255, 255, 255, 0.5);
            backdrop-filter: blur(10px);
            color: #4a5568;
        }

        .modal-btn-cancel:hover {
            background: rgba(255, 255, 255, 0.7);
        }

        .modal-btn-save {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
        }

        .modal-btn-save:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(102, 126, 234, 0.4);
        }

        /* Report View Modal */
        .report-modal-content {
            max-width: 800px;
        }

        .report-full-text {
            background: rgba(255, 255, 255, 0.5);
            padding: 20px;
            border-radius: 12px;
            color: #2d3748;
            line-height: 1.8;
            font-size: 1rem;
            max-height: 400px;
            overflow-y: auto;
        }

        /* Responsive Design */
        @media (max-width: 1024px) {
            .main-grid {
                grid-template-columns: 1fr;
            }

            .profile-sidebar {
                border-right: none;
                border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            }

            body {
                padding: 120px 15px 30px;
            }
        }

        @media (max-width: 640px) {
            .documents-grid {
                grid-template-columns: 1fr;
            }

            .section-card,
            .content-area,
            .profile-sidebar {
                padding: 24px 20px;
            }

            .page-header {
                padding: 30px 20px;
            }

            .page-header h1 {
                font-size: 2rem;
            }

            .section-title {
                font-size: 1.25rem;
            }

            .quick-actions {
                padding: 16px 20px;
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
        <a href="child_management.php" class="back-btn">
            <span>←</span>
            <span>Back to Children</span>
        </a>

        <?php if (!empty($success_message)): ?>
            <div class="success-alert">
                <span>✅</span>
                <span><?php echo htmlspecialchars($success_message); ?></span>
            </div>
        <?php endif; ?>

        <div class="glass-card">
            <!-- Page Header -->
            <div class="page-header">
                <h1>Edit Child Profile</h1>
                <p>Update information and documents</p>
            </div>

            <!-- Quick Actions Bar -->
            <div class="quick-actions">
                <a href="staff_add_report.php?child_id=<?php echo $child_id; ?>" class="action-btn action-btn-primary">
                    <span>📊</span>
                    <span>Add Progress Report</span>
                </a>
                <a href="staff_add_event.php?child_id=<?php echo $child_id; ?>" class="action-btn action-btn-primary">
                    <span>📅</span>
                    <span>Add Calendar Event</span>
                </a>
            </div>

            <div class="main-grid">
                <!-- Profile Sidebar -->
                <div class="profile-sidebar">
                    <div class="profile-image-wrapper">
                        <div class="profile-image-container">
                            <?php if (!empty($child['profile_picture'])): ?>
                                <img src="../<?php echo htmlspecialchars($child['profile_picture']); ?>" 
                                     alt="<?php echo htmlspecialchars($child['first_name']); ?>" 
                                     class="profile-image">
                            <?php else: ?>
                                <div class="profile-initials"><?php echo $initials; ?></div>
                            <?php endif; ?>
                        </div>
                        <div class="edit-image-overlay" onclick="editProfileImage()">
                            📷
                        </div>
                    </div>

                    <div class="profile-name">
                        <h2><?php echo htmlspecialchars($child['first_name'] . ' ' . $child['last_name']); ?></h2>
                        <button class="edit-name-btn" onclick="editName()">
                            <span>✏️</span>
                            <span>Edit Name</span>
                        </button>
                    </div>

                    <div class="info-group">
                        <div class="info-row">
                            <span class="info-label">Age</span>
                            <div class="info-value">
                                <span><?php echo $age; ?> years old</span>
                                <button class="icon-btn" onclick="editDOB()">✏️</button>
                            </div>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Gender</span>
                            <div class="info-value">
                                <span><?php echo htmlspecialchars($child['gender']); ?></span>
                                <button class="icon-btn" onclick="editGender()">✏️</button>
                            </div>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Date of Birth</span>
                            <span class="info-value"><?php echo date('M d, Y', strtotime($child['dob'])); ?></span>
                        </div>
                    </div>
                </div>

                <!-- Content Area -->
                <div class="content-area">
                    <!-- About Me Section -->
                    <div class="section-card">
                        <div class="section-header">
                            <h2 class="section-title">About Me</h2>
                            <button class="icon-btn" onclick="editAboutMe()">✏️</button>
                        </div>
                        <div class="section-content">
                            <?php if (!empty($child['about_me'])): ?>
                                <?php echo nl2br(htmlspecialchars($child['about_me'])); ?>
                            <?php else: ?>
                                <div class="empty-state">No information provided yet</div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Dreams & Aspirations Section -->
                    <div class="section-card">
                        <div class="section-header">
                            <h2 class="section-title">Dreams & Aspirations</h2>
                            <button class="icon-btn" onclick="editAspiration()">✏️</button>
                        </div>
                        <div class="section-content">
                            <?php if (!empty($child['aspiration'])): ?>
                                <?php echo nl2br(htmlspecialchars($child['aspiration'])); ?>
                            <?php else: ?>
                                <div class="empty-state">No aspirations provided yet</div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Progress Reports Section (NEW) -->
                    <div class="section-card">
                        <div class="section-header">
                            <h2 class="section-title">Progress Reports</h2>
                            <a href="staff_add_report.php?child_id=<?php echo $child_id; ?>" class="icon-btn" title="Add Report">+</a>
                        </div>
                        <?php if (count($reports) > 0): ?>
                            <div class="reports-list">
                                <?php foreach ($reports as $report): ?>
                                    <div class="report-item">
                                        <div class="report-header">
                                            <div class="report-date">
                                                <?php echo date('F j, Y', strtotime($report['report_date'])); ?>
                                            </div>
                                        </div>
                                        <div class="report-preview">
                                            <?php 
                                            $preview = strip_tags($report['report_text']);
                                            echo htmlspecialchars(substr($preview, 0, 150)) . (strlen($preview) > 150 ? '...' : ''); 
                                            ?>
                                        </div>
                                        <div class="report-footer">
                                            <span>By: <?php echo htmlspecialchars($report['uploaded_by_name'] ?? 'Staff'); ?></span>
                                            <button class="view-report-btn" onclick="viewReport(<?php echo htmlspecialchars(json_encode($report)); ?>)">
                                                View Full Report
                                            </button>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="empty-state">No progress reports yet. Click the + button to add one!</div>
                        <?php endif; ?>
                    </div>

                    <!-- Documents Section -->
                    <div class="section-card">
                        <div class="section-header">
                            <h2 class="section-title">Documents</h2>
                        </div>
                        <div class="documents-grid">
                            <div class="upload-card" onclick="uploadDocument()">
                                <div class="upload-icon">+</div>
                                <div class="upload-text">Upload Document</div>
                            </div>

                            <?php foreach ($documents as $doc): ?>
                                <div class="doc-card">
                                    <div class="doc-category"><?php echo htmlspecialchars($doc['category']); ?></div>
                                    <div class="doc-title">Document</div>
                                    <div class="doc-date">
                                        <?php echo date('M d, Y', strtotime($doc['upload_date'])); ?>
                                    </div>
                                    <div class="doc-actions">
                                        <a href="../<?php echo htmlspecialchars($doc['file_path']); ?>" 
                                           target="_blank" 
                                           class="doc-btn">View</a>
                                        <a href="../<?php echo htmlspecialchars($doc['file_path']); ?>" 
                                           download 
                                           class="doc-btn">Download</a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Name Modal -->
    <div class="modal" id="nameModal">
        <div class="modal-content">
            <h3 class="modal-title">Edit Name</h3>
            <form id="nameForm">
                <div class="form-group">
                    <label class="form-label">First Name *</label>
                    <input type="text" class="form-input" id="firstName" placeholder="Enter first name" value="<?php echo htmlspecialchars($child['first_name']); ?>" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Last Name *</label>
                    <input type="text" class="form-input" id="lastName" placeholder="Enter last name" value="<?php echo htmlspecialchars($child['last_name']); ?>" required>
                </div>
                <div class="modal-actions">
                    <button type="button" class="modal-btn modal-btn-cancel" onclick="closeModal('nameModal')">Cancel</button>
                    <button type="submit" class="modal-btn modal-btn-save">Save Changes</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit DOB Modal -->
    <div class="modal" id="dobModal">
        <div class="modal-content">
            <h3 class="modal-title">Edit Date of Birth</h3>
            <form id="dobForm">
                <div class="form-group">
                    <label class="form-label">Date of Birth *</label>
                    <input type="date" class="form-input" id="dob" value="<?php echo $child['dob']; ?>" required>
                </div>
                <div class="modal-actions">
                    <button type="button" class="modal-btn modal-btn-cancel" onclick="closeModal('dobModal')">Cancel</button>
                    <button type="submit" class="modal-btn modal-btn-save">Save Changes</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Gender Modal -->
    <div class="modal" id="genderModal">
        <div class="modal-content">
            <h3 class="modal-title">Edit Gender</h3>
            <form id="genderForm">
                <div class="form-group">
                    <label class="form-label">Gender *</label>
                    <select class="form-select" id="gender" required>
                        <option value="Male" <?php echo $child['gender'] === 'Male' ? 'selected' : ''; ?>>Male</option>
                        <option value="Female" <?php echo $child['gender'] === 'Female' ? 'selected' : ''; ?>>Female</option>
                        <option value="Other" <?php echo $child['gender'] === 'Other' ? 'selected' : ''; ?>>Other</option>
                    </select>
                </div>
                <div class="modal-actions">
                    <button type="button" class="modal-btn modal-btn-cancel" onclick="closeModal('genderModal')">Cancel</button>
                    <button type="submit" class="modal-btn modal-btn-save">Save Changes</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit About Me Modal -->
    <div class="modal" id="aboutModal">
        <div class="modal-content">
            <h3 class="modal-title">Edit About Me</h3>
            <form id="aboutForm">
                <div class="form-group">
                    <label class="form-label">About Me</label>
                    <textarea class="form-textarea" id="aboutMe" placeholder="Tell us about this child..."><?php echo htmlspecialchars($child['about_me']); ?></textarea>
                </div>
                <div class="modal-actions">
                    <button type="button" class="modal-btn modal-btn-cancel" onclick="closeModal('aboutModal')">Cancel</button>
                    <button type="submit" class="modal-btn modal-btn-save">Save Changes</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Aspiration Modal -->
    <div class="modal" id="aspirationModal">
        <div class="modal-content">
            <h3 class="modal-title">Edit Dreams & Aspirations</h3>
            <form id="aspirationForm">
                <div class="form-group">
                    <label class="form-label">Aspirations</label>
                    <textarea class="form-textarea" id="aspiration" placeholder="What are their dreams and aspirations..."><?php echo htmlspecialchars($child['aspiration']); ?></textarea>
                </div>
                <div class="modal-actions">
                    <button type="button" class="modal-btn modal-btn-cancel" onclick="closeModal('aspirationModal')">Cancel</button>
                    <button type="submit" class="modal-btn modal-btn-save">Save Changes</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Upload Document Modal -->
    <div class="modal" id="uploadModal">
        <div class="modal-content">
            <h3 class="modal-title">Upload Document</h3>
            <form id="uploadForm">
                <div class="form-group">
                    <label class="form-label">Category *</label>
                    <select class="form-select" id="docCategory" required>
                        <option value="">Select Category</option>
                        <option value="Health">Health</option>
                        <option value="Education">Education</option>
                        <option value="Achievement">Achievement</option>
                        <option value="Other">Other</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Document File *</label>
                    <input type="file" class="form-input" id="docFile" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx" required>
                </div>
                <div class="modal-actions">
                    <button type="button" class="modal-btn modal-btn-cancel" onclick="closeModal('uploadModal')">Cancel</button>
                    <button type="submit" class="modal-btn modal-btn-save">Upload</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Profile Image Modal -->
    <div class="modal" id="imageModal">
        <div class="modal-content">
            <h3 class="modal-title">Edit Profile Picture</h3>
            <form id="imageForm">
                <div class="form-group">
                    <label class="form-label">Profile Picture *</label>
                    <input type="file" class="form-input" id="profilePic" accept="image/*" required>
                </div>
                <div class="modal-actions">
                    <button type="button" class="modal-btn modal-btn-cancel" onclick="closeModal('imageModal')">Cancel</button>
                    <button type="submit" class="modal-btn modal-btn-save">Upload</button>
                </div>
            </form>
        </div>
    </div>

    <!-- View Report Modal (NEW) -->
    <div class="modal" id="reportViewModal">
        <div class="modal-content report-modal-content">
            <h3 class="modal-title" id="reportModalTitle">Progress Report</h3>
            <div class="form-group">
                <label class="form-label">Report Date</label>
                <div id="reportModalDate" style="color: #4a5568; font-weight: 600;"></div>
            </div>
            <div class="form-group">
                <label class="form-label">Report Content</label>
                <div class="report-full-text" id="reportModalContent"></div>
            </div>
            <div class="form-group">
                <label class="form-label">Created By</label>
                <div id="reportModalAuthor" style="color: #4a5568; font-weight: 600;"></div>
            </div>
            <div class="modal-actions">
                <button type="button" class="modal-btn modal-btn-cancel" onclick="closeModal('reportViewModal')">Close</button>
            </div>
        </div>
    </div>

    <script>
        const childId = <?php echo $child_id; ?>;

        function openModal(modalId) {
            document.getElementById(modalId).classList.add('active');
        }

        function closeModal(modalId) {
            document.getElementById(modalId).classList.remove('active');
        }

        function editName() { openModal('nameModal'); }
        function editDOB() { openModal('dobModal'); }
        function editGender() { openModal('genderModal'); }
        function editAboutMe() { openModal('aboutModal'); }
        function editAspiration() { openModal('aspirationModal'); }
        function uploadDocument() { openModal('uploadModal'); }
        function editProfileImage() { openModal('imageModal'); }

        // View Report Function (NEW)
        function viewReport(report) {
            document.getElementById('reportModalTitle').textContent = 'Progress Report';
            document.getElementById('reportModalDate').textContent = new Date(report.report_date).toLocaleDateString('en-US', {
                year: 'numeric',
                month: 'long',
                day: 'numeric'
            });
            document.getElementById('reportModalContent').innerHTML = report.report_text.replace(/\n/g, '<br>');
            document.getElementById('reportModalAuthor').textContent = report.uploaded_by_name || 'Staff';
            openModal('reportViewModal');
        }

        // Close modal when clicking outside
        document.querySelectorAll('.modal').forEach(modal => {
            modal.addEventListener('click', function(e) {
                if (e.target === this) {
                    this.classList.remove('active');
                }
            });
        });

        // Edit Name Form
        document.getElementById('nameForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            const firstName = document.getElementById('firstName').value;
            const lastName = document.getElementById('lastName').value;

            try {
                const response = await fetch('handle_child_updates.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        child_id: childId,
                        field: 'name',
                        first_name: firstName,
                        last_name: lastName
                    })
                });

                const data = await response.json();
                if (data.success) {
                    closeModal('nameModal');
                    alert('✅ Name updated successfully!');
                    location.reload();
                } else {
                    alert('❌ Error: ' + data.message);
                }
            } catch (error) {
                alert('❌ Error updating name');
            }
        });

        // Edit DOB Form
        document.getElementById('dobForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            const dob = document.getElementById('dob').value;

            try {
                const response = await fetch('handle_child_updates.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        child_id: childId,
                        field: 'dob',
                        dob: dob
                    })
                });

                const data = await response.json();
                if (data.success) {
                    closeModal('dobModal');
                    alert('✅ Date of birth updated successfully!');
                    location.reload();
                } else {
                    alert('❌ Error: ' + data.message);
                }
            } catch (error) {
                alert('❌ Error updating date of birth');
            }
        });

        // Edit Gender Form
        document.getElementById('genderForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            const gender = document.getElementById('gender').value;

            try {
                const response = await fetch('handle_child_updates.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        child_id: childId,
                        field: 'gender',
                        gender: gender
                    })
                });

                const data = await response.json();
                if (data.success) {
                    closeModal('genderModal');
                    alert('✅ Gender updated successfully!');
                    location.reload();
                } else {
                    alert('❌ Error: ' + data.message);
                }
            } catch (error) {
                alert('❌ Error updating gender');
            }
        });

        // Edit About Me Form
        document.getElementById('aboutForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            const aboutMe = document.getElementById('aboutMe').value;

            try {
                const response = await fetch('handle_child_updates.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        child_id: childId,
                        field: 'about_me',
                        about_me: aboutMe
                    })
                });

                const data = await response.json();
                if (data.success) {
                    closeModal('aboutModal');
                    alert('✅ About Me updated successfully!');
                    location.reload();
                } else {
                    alert('❌ Error: ' + data.message);
                }
            } catch (error) {
                alert('❌ Error updating About Me');
            }
        });

        // Edit Aspiration Form
        document.getElementById('aspirationForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            const aspiration = document.getElementById('aspiration').value;

            try {
                const response = await fetch('handle_child_updates.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        child_id: childId,
                        field: 'aspiration',
                        aspiration: aspiration
                    })
                });

                const data = await response.json();
                if (data.success) {
                    closeModal('aspirationModal');
                    alert('✅ Aspirations updated successfully!');
                    location.reload();
                } else {
                    alert('❌ Error: ' + data.message);
                }
            } catch (error) {
                alert('❌ Error updating aspirations');
            }
        });

        // Upload Document Form
        document.getElementById('uploadForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            const category = document.getElementById('docCategory').value;
            const file = document.getElementById('docFile').files[0];

            const formData = new FormData();
            formData.append('action', 'upload_document');
            formData.append('child_id', childId);
            formData.append('category', category);
            formData.append('document', file);

            try {
                const response = await fetch('handle_child_updates.php', {
                    method: 'POST',
                    body: formData
                });

                const data = await response.json();
                if (data.success) {
                    closeModal('uploadModal');
                    alert('✅ ' + data.message);
                    location.reload();
                } else {
                    alert('❌ Error: ' + data.message);
                }
            } catch (error) {
                alert('❌ Error uploading document');
            }
        });

        // Upload Profile Picture Form
        document.getElementById('imageForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            const file = document.getElementById('profilePic').files[0];

            const formData = new FormData();
            formData.append('action', 'update_profile_picture');
            formData.append('child_id', childId);
            formData.append('profile_picture', file);

            try {
                const response = await fetch('handle_child_updates.php', {
                    method: 'POST',
                    body: formData
                });

                const data = await response.json();
                if (data.success) {
                    closeModal('imageModal');
                    alert('✅ Profile picture updated successfully!');
                    location.reload();
                } else {
                    alert('❌ Error: ' + data.message);
                }
            } catch (error) {
                alert('❌ Error updating profile picture');
            }
        });
    </script>
</body>
</html>