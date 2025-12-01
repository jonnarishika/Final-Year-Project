<?php
session_start();
require_once __DIR__ . '/db_config.php';

// Get child ID from URL
if (!isset($_GET['child_id']) || empty($_GET['child_id'])) {
    header("Location: all_children_profiles_sponser.php");
    exit();
}

$child_id = intval($_GET['child_id']);

// Check if user is logged in and verify they are a sponsor
$is_logged_in = isset($_SESSION['user_id']);
$user_role = null;
$sponsor_id = null;

if ($is_logged_in) {
    $user_id = $_SESSION['user_id'];
    
    // Get user role
    $role_query = "SELECT user_role FROM users WHERE user_id = ?";
    $role_stmt = $conn->prepare($role_query);
    $role_stmt->bind_param("i", $user_id);
    $role_stmt->execute();
    $role_result = $role_stmt->get_result();
    
    if ($role_result->num_rows > 0) {
        $role_data = $role_result->fetch_assoc();
        $user_role = $role_data['user_role'];
    }
    $role_stmt->close();
    
    // Get sponsor_id if user is a sponsor
    if ($user_role === 'Sponsor') {
        $sponsor_query = "SELECT sponsor_id FROM sponsors WHERE user_id = ?";
        $sponsor_stmt = $conn->prepare($sponsor_query);
        $sponsor_stmt->bind_param("i", $user_id);
        $sponsor_stmt->execute();
        $sponsor_result = $sponsor_stmt->get_result();
        
        if ($sponsor_result->num_rows > 0) {
            $sponsor_data = $sponsor_result->fetch_assoc();
            $sponsor_id = intval($sponsor_data['sponsor_id']);
        }
        $sponsor_stmt->close();
    }
    
    // Initialize sidebar for sponsors only
    if ($user_role === 'Sponsor') {
        require_once __DIR__ . '/components/sidebar_config.php';
        $sidebar_menu = initSidebar('sponsor', 'child_profile.php');
        $logout_path = 'signup_and_login/logout.php';
    }
}

// Fetch child details (removed sponsor_id from query as we don't need to show sponsorship status)
$child_query = "SELECT child_id, first_name, last_name, dob, gender, about_me, aspiration, 
                profile_picture, profile_video, status, created_at 
                FROM children WHERE child_id = ?";
$child_stmt = $conn->prepare($child_query);
$child_stmt->bind_param("i", $child_id);
$child_stmt->execute();
$child_result = $child_stmt->get_result();

if ($child_result->num_rows === 0) {
    $child_stmt->close();
    $conn->close();
    header("Location: all_children_profiles_sponser.php");
    exit();
}

$child = $child_result->fetch_assoc();
$child_stmt->close();

// Calculate age
$dob = new DateTime($child['dob']);
$today = new DateTime();
$age = $today->diff($dob)->y;

// Get uploads (only if logged in)
$health_uploads = [];
$education_uploads = [];
$achievement_uploads = [];

if ($is_logged_in) {
    $uploads_query = "SELECT upload_id, category, file_path, upload_date 
                      FROM child_uploads 
                      WHERE child_id = ? 
                      ORDER BY upload_date DESC";
    $uploads_stmt = $conn->prepare($uploads_query);
    $uploads_stmt->bind_param("i", $child_id);
    $uploads_stmt->execute();
    $uploads_result = $uploads_stmt->get_result();
    
    while ($upload = $uploads_result->fetch_assoc()) {
        switch ($upload['category']) {
            case 'Health':
                $health_uploads[] = $upload;
                break;
            case 'Education':
                $education_uploads[] = $upload;
                break;
            case 'Achievement':
                $achievement_uploads[] = $upload;
                break;
        }
    }
    $uploads_stmt->close();
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($child['first_name'] . ' ' . $child['last_name']); ?> - Profile</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 25%, #7e8ba3 50%, #89a6c7 75%, #a8c5e7 100%);
            min-height: 100vh;
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

        /* Main Wrapper */
        .main-wrapper {
            margin-left: 0;
            margin-top: <?php echo ($is_logged_in && $user_role === 'Sponsor') ? '80px' : '0'; ?>;
            transition: margin-left 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            z-index: 1;
            padding: 40px 20px;
        }

        .main-wrapper.sidebar-open {
            margin-left: 280px;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            position: relative;
        }

        /* Glass Card */
        .glass-card {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border-radius: 24px;
            border: 1px solid rgba(255, 255, 255, 0.2);
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1),
                        inset 0 1px 0 rgba(255, 255, 255, 0.2);
            overflow: hidden;
            margin-bottom: 30px;
        }

        /* Profile Header */
        .profile-header {
            background: linear-gradient(135deg, rgba(62, 106, 188, 0.4) 0%, rgba(105, 155, 224, 0.3) 100%);
            backdrop-filter: blur(10px);
            padding: 50px;
            display: flex;
            gap: 40px;
            align-items: center;
            border-bottom: 1px solid rgba(255, 255, 255, 0.15);
        }

        .profile-image-wrapper {
            flex-shrink: 0;
        }

        .profile-image {
            width: 200px;
            height: 200px;
            border-radius: 20px;
            overflow: hidden;
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.2), rgba(255, 255, 255, 0.1));
            border: 3px solid rgba(255, 255, 255, 0.3);
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.2);
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .profile-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .profile-image .initials {
            font-size: 4rem;
            font-weight: 800;
            color: white;
            text-shadow: 0 2px 10px rgba(0, 0, 0, 0.2);
        }

        .profile-info {
            flex: 1;
            color: white;
        }

        .profile-name {
            font-size: 3rem;
            font-weight: 800;
            margin-bottom: 10px;
            text-shadow: 0 2px 10px rgba(0, 0, 0, 0.2);
        }

        .profile-meta {
            display: flex;
            gap: 30px;
            margin-bottom: 25px;
            flex-wrap: wrap;
        }

        .meta-item {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 1.1rem;
            background: rgba(255, 255, 255, 0.1);
            padding: 10px 20px;
            border-radius: 12px;
            backdrop-filter: blur(10px);
        }

        .donate-button {
            display: inline-flex;
            align-items: center;
            gap: 12px;
            padding: 16px 32px;
            background: linear-gradient(135deg, #fbbf24, #f59e0b);
            color: white;
            text-decoration: none;
            border-radius: 14px;
            font-size: 1.1rem;
            font-weight: 700;
            border: none;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 4px 20px rgba(251, 191, 36, 0.4);
        }

        .donate-button:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 30px rgba(251, 191, 36, 0.6);
        }

        /* Content Section */
        .content-section {
            padding: 40px 50px;
            background: rgba(255, 255, 255, 0.05);
        }

        .section-title {
            font-size: 1.8rem;
            font-weight: 700;
            color: white;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid rgba(255, 255, 255, 0.2);
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .section-content {
            color: rgba(255, 255, 255, 0.95);
            font-size: 1.1rem;
            line-height: 1.8;
            background: rgba(255, 255, 255, 0.05);
            padding: 25px;
            border-radius: 16px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            margin-bottom: 40px;
        }

        .empty-message {
            color: rgba(255, 255, 255, 0.6);
            font-style: italic;
            text-align: center;
            padding: 30px;
        }

        /* Uploads Grid */
        .uploads-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 40px;
        }

        .upload-card {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 16px;
            padding: 25px;
            transition: all 0.3s ease;
            cursor: pointer;
        }

        .upload-card:hover {
            background: rgba(255, 255, 255, 0.15);
            transform: translateY(-5px);
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.2);
        }

        .upload-title {
            color: white;
            font-size: 1rem;
            font-weight: 600;
            margin-bottom: 10px;
            text-align: center;
        }

        .upload-date {
            color: rgba(255, 255, 255, 0.7);
            font-size: 0.85rem;
            text-align: center;
        }

        .upload-actions {
            display: flex;
            gap: 10px;
            margin-top: 15px;
        }

        .upload-btn {
            flex: 1;
            padding: 8px 16px;
            border: 1px solid rgba(255, 255, 255, 0.3);
            background: rgba(255, 255, 255, 0.1);
            color: white;
            text-decoration: none;
            text-align: center;
            border-radius: 10px;
            font-size: 0.9rem;
            font-weight: 600;
            transition: all 0.3s ease;
            display: inline-block;
        }

        .upload-btn:hover {
            background: rgba(255, 255, 255, 0.2);
            transform: translateY(-2px);
        }

        /* Video Section */
        .video-section {
            margin-top: 40px;
        }

        .video-wrapper {
            background: rgba(0, 0, 0, 0.3);
            border-radius: 16px;
            overflow: hidden;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .video-wrapper video {
            width: 100%;
            max-height: 600px;
            display: block;
        }

        .video-caption {
            color: rgba(255, 255, 255, 0.8);
            text-align: center;
            padding: 15px;
            font-size: 1rem;
            background: rgba(255, 255, 255, 0.05);
        }

        /* Back Button */
        .back-button {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            padding: 12px 24px;
            background: rgba(255, 255, 255, 0.1);
            color: white;
            text-decoration: none;
            border-radius: 12px;
            font-weight: 600;
            margin-bottom: 30px;
            transition: all 0.3s ease;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .back-button:hover {
            background: rgba(255, 255, 255, 0.2);
            transform: translateX(-5px);
        }

        /* Login Required Message */
        .login-required {
            background: rgba(251, 191, 36, 0.2);
            border: 2px solid rgba(251, 191, 36, 0.4);
            color: white;
            padding: 20px 30px;
            border-radius: 16px;
            text-align: center;
            margin: 40px 0;
        }

        .login-required-title {
            font-size: 1.3rem;
            font-weight: 700;
            margin-bottom: 10px;
        }

        .login-required-text {
            font-size: 1rem;
            margin-bottom: 20px;
            opacity: 0.9;
        }

        .login-button {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            padding: 12px 32px;
            background: white;
            color: #2a5298;
            text-decoration: none;
            border-radius: 12px;
            font-weight: 700;
            transition: all 0.3s ease;
        }

        .login-button:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 24px rgba(255, 255, 255, 0.3);
        }

        @media (max-width: 1200px) {
            .main-wrapper.sidebar-open {
                margin-left: 0;
            }
        }

        @media (max-width: 768px) {
            .profile-header {
                flex-direction: column;
                text-align: center;
                padding: 30px 25px;
            }

            .profile-name {
                font-size: 2rem;
            }

            .profile-meta {
                justify-content: center;
            }

            .content-section {
                padding: 30px 25px;
            }

            .uploads-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <?php if ($is_logged_in && $user_role === 'Sponsor'): ?>
        <?php 
        include __DIR__ . '/components/header.php';
        include __DIR__ . '/components/sidebar.php'; 
        ?>
    <?php else: ?>
        <?php include __DIR__ . '/components/non_loggedin_header.php'; ?>
    <?php endif; ?>

    <div class="main-wrapper" id="mainWrapper">
        <div class="container">
            <a href="all_children_profiles_sponser.php" class="back-button">
                <span>←</span>
                <span>Back to All Children</span>
            </a>

            <div class="glass-card">
                <div class="profile-header">
                    <div class="profile-image-wrapper">
                        <div class="profile-image">
                            <?php if (!empty($child['profile_picture'])): ?>
                                <img src="<?php echo htmlspecialchars($child['profile_picture']); ?>" 
                                     alt="<?php echo htmlspecialchars($child['first_name'] . ' ' . $child['last_name']); ?>"
                                     onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                                <span class="initials" style="display:none;">
                                    <?php echo strtoupper($child['first_name'][0] . $child['last_name'][0]); ?>
                                </span>
                            <?php else: ?>
                                <span class="initials">
                                    <?php echo strtoupper($child['first_name'][0] . $child['last_name'][0]); ?>
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="profile-info">
                        <h1 class="profile-name">
                            <?php echo htmlspecialchars($child['first_name'] . ' ' . $child['last_name']); ?>
                        </h1>

                        <div class="profile-meta">
                            <div class="meta-item">
                                <span><?php echo $age; ?> years old</span>
                            </div>
                            <div class="meta-item">
                                <span><?php echo htmlspecialchars($child['gender']); ?></span>
                            </div>
                        </div>

                        <?php if ($user_role === 'Sponsor'): ?>
                            <a href="sponser/sponsor_child.php?child_id=<?php echo $child_id; ?>"
                               class="donate-button">
                                <span>Sponsor This Child</span>
                            </a>
                        <?php elseif (!$is_logged_in): ?>
                            <a href="signup_and_login/login.php?redirect=<?php echo urlencode('child_profile.php?child_id=' . $child_id); ?>" 
                               class="donate-button">
                                <span>Sponsor This Child</span>
                            </a>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="content-section">
                    <!-- About Me Section -->
                    <h2 class="section-title">About Me</h2>
                    <div class="section-content">
                        <?php if (!empty($child['about_me'])): ?>
                            <?php echo nl2br(htmlspecialchars($child['about_me'])); ?>
                        <?php else: ?>
                            <p class="empty-message">No information available yet.</p>
                        <?php endif; ?>
                    </div>

                    <!-- Aspiration Section -->
                    <h2 class="section-title">My Dreams & Aspirations</h2>
                    <div class="section-content">
                        <?php if (!empty($child['aspiration'])): ?>
                            <?php echo nl2br(htmlspecialchars($child['aspiration'])); ?>
                        <?php else: ?>
                            <p class="empty-message">Aspirations will be added soon.</p>
                        <?php endif; ?>
                    </div>

                    <?php if ($is_logged_in): ?>
                        <!-- Health Section -->
                        <h2 class="section-title">Health Records</h2>
                        <?php if (count($health_uploads) > 0): ?>
                            <div class="uploads-grid">
                                <?php foreach ($health_uploads as $upload): ?>
                                    <div class="upload-card">
                                        <div class="upload-title">Health Document</div>
                                        <div class="upload-date">
                                            <?php echo date('M d, Y', strtotime($upload['upload_date'])); ?>
                                        </div>
                                        <div class="upload-actions">
                                            <a href="<?php echo htmlspecialchars($upload['file_path']); ?>" 
                                               target="_blank" class="upload-btn">View</a>
                                            <a href="<?php echo htmlspecialchars($upload['file_path']); ?>" 
                                               download class="upload-btn">Download</a>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="section-content">
                                <p class="empty-message">No health records uploaded yet.</p>
                            </div>
                        <?php endif; ?>

                        <!-- Education Section -->
                        <h2 class="section-title">Education Progress</h2>
                        <?php if (count($education_uploads) > 0): ?>
                            <div class="uploads-grid">
                                <?php foreach ($education_uploads as $upload): ?>
                                    <div class="upload-card">
                                        <div class="upload-title">Education Document</div>
                                        <div class="upload-date">
                                            <?php echo date('M d, Y', strtotime($upload['upload_date'])); ?>
                                        </div>
                                        <div class="upload-actions">
                                            <a href="<?php echo htmlspecialchars($upload['file_path']); ?>" 
                                               target="_blank" class="upload-btn">View</a>
                                            <a href="<?php echo htmlspecialchars($upload['file_path']); ?>" 
                                               download class="upload-btn">Download</a>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="section-content">
                                <p class="empty-message">No education records uploaded yet.</p>
                            </div>
                        <?php endif; ?>

                        <!-- Achievement Section -->
                        <h2 class="section-title">Achievements</h2>
                        <?php if (count($achievement_uploads) > 0): ?>
                            <div class="uploads-grid">
                                <?php foreach ($achievement_uploads as $upload): ?>
                                    <div class="upload-card">
                                        <div class="upload-title">Achievement Certificate</div>
                                        <div class="upload-date">
                                            <?php echo date('M d, Y', strtotime($upload['upload_date'])); ?>
                                        </div>
                                        <div class="upload-actions">
                                            <a href="<?php echo htmlspecialchars($upload['file_path']); ?>" 
                                               target="_blank" class="upload-btn">View</a>
                                            <a href="<?php echo htmlspecialchars($upload['file_path']); ?>" 
                                               download class="upload-btn">Download</a>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="section-content">
                                <p class="empty-message">No achievements uploaded yet.</p>
                            </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <!-- Login Required Message for Documents -->
                        <div class="login-required">
                            <div class="login-required-title">Want to See More?</div>
                            <div class="login-required-text">
                                Login to view health records, education progress, and achievements
                            </div>
                            <a href="signup_and_login/login_template.php?redirect=child_profile.php?child_id=<?php echo $child_id; ?>" 
                               class="login-button">
                                <span>Login to View Details</span>
                                <span>→</span>
                            </a>
                        </div>
                    <?php endif; ?>

                    <!-- Video Section (Visible to Everyone) -->
                    <?php if (!empty($child['profile_video'])): ?>
                        <div class="video-section">
                            <h2 class="section-title">Video Introduction</h2>
                            <div class="video-wrapper">
                                <video controls preload="metadata">
                                    <source src="<?php echo htmlspecialchars($child['profile_video']); ?>" type="video/mp4">
                                    Your browser does not support the video tag.
                                </video>
                                <div class="video-caption">
                                    Watch <?php echo htmlspecialchars($child['first_name']); ?> introduce themselves!
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <?php if ($is_logged_in && $user_role === 'Sponsor'): ?>
        <?php include __DIR__ . '/components/common_scripts.php'; ?>
    <?php endif; ?>

    <script>
        // Sidebar toggle for logged-in sponsors
        <?php if ($is_logged_in && $user_role === 'Sponsor'): ?>
        document.addEventListener('DOMContentLoaded', function() {
            const mainWrapper = document.getElementById('mainWrapper');
            const sidebar = document.querySelector('.sidebar');
            
            if (sidebar && sidebar.classList.contains('open')) {
                mainWrapper.classList.add('sidebar-open');
            }
        });
        <?php endif; ?>
    </script>
</body>
</html>