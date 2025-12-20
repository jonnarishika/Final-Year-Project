<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

require_once __DIR__ . '/../db_config.php';
require_once __DIR__ . '/../components/sidebar_config.php';

// Check if database connection exists
if (!isset($conn)) {
    die("Database connection failed. Please check db_config.php");
}

$user_id = $_SESSION['user_id'];

// Get sponsor information
$query = "SELECT u.user_id, u.username, u.email, u.phone_no, u.created_at, 
                 s.sponsor_id, s.first_name, s.last_name, s.dob, s.address, s.profile_picture
          FROM users u
          INNER JOIN sponsors s ON u.user_id = s.user_id
          WHERE u.user_id = ?";

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die("Sponsor not found. Please make sure this user account is registered as a sponsor.");
}

$user_data = $result->fetch_assoc();
$sponsor_id = intval($user_data['sponsor_id']);
$stmt->close();

// Additional validation
if ($sponsor_id <= 0) {
    die("Invalid sponsor ID. Please contact administrator.");
}

// ✅ FIXED: Get dashboard statistics using children.sponsor_id (Method A)
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM children WHERE sponsor_id = ?");
$stmt->bind_param("i", $sponsor_id);
$stmt->execute();
$sponsored_children = $stmt->get_result()->fetch_assoc()['count'];
$stmt->close();

$stmt = $conn->prepare("SELECT COALESCE(SUM(amount), 0) as total FROM donations WHERE sponsor_id = ?");
$stmt->bind_param("i", $sponsor_id);
$stmt->execute();
$total_donated = $stmt->get_result()->fetch_assoc()['total'];
$stmt->close();

// ✅ FIXED: Reports using children.sponsor_id
$stmt = $conn->prepare("
    SELECT COUNT(DISTINCT cr.report_id) as count 
    FROM child_reports cr
    INNER JOIN children c ON cr.child_id = c.child_id
    WHERE c.sponsor_id = ? 
    AND cr.report_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
");
$stmt->bind_param("i", $sponsor_id);
$stmt->execute();
$new_reports = $stmt->get_result()->fetch_assoc()['count'];
$stmt->close();

// Check if timeline_events table exists
$table_check = $conn->query("SHOW TABLES LIKE 'timeline_events'");
if ($table_check && $table_check->num_rows > 0) {
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM timeline_events WHERE sponsor_id = ? AND created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)");
    $stmt->bind_param("i", $sponsor_id);
    $stmt->execute();
    $recent_events = $stmt->get_result()->fetch_assoc()['count'];
    $stmt->close();
} else {
    $recent_events = 0;
}

$conn->close();

// Calculate initials
$initials = strtoupper(substr($user_data['first_name'], 0, 1) . substr($user_data['last_name'], 0, 1));

// Initialize sidebar menu for sponsor
$sidebar_menu = initSidebar('sponsor', 'sponser_main_page.php');

// Set logout path
$logout_path = '../signup_and_login/logout.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sponsor Dashboard</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
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
            position: relative;
            overflow-x: hidden;
        }

        body::before {
            content: '';
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 1000px;
            height: 1000px;
            background: radial-gradient(circle at center, 
                rgba(254, 243, 199, 0.6) 0%,
                rgba(253, 230, 138, 0.55) 15%,
                rgba(252, 211, 77, 0.5) 25%, 
                rgba(251, 191, 36, 0.45) 35%,
                rgba(251, 191, 36, 0.35) 45%,
                rgba(252, 211, 77, 0.25) 55%,
                transparent 70%);
            pointer-events: none;
            z-index: 0;
            filter: blur(60px);
            animation: pulseAura 6s ease-in-out infinite;
        }

        @keyframes pulseAura {
            0%, 100% {
                transform: translate(-50%, -50%) scale(1);
                opacity: 0.7;
            }
            50% {
                transform: translate(-50%, -50%) scale(1.1);
                opacity: 0.85;
            }
        }

        body::after {
            content: '';
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 600px;
            height: 600px;
            background: radial-gradient(circle at center, 
                rgba(251, 191, 36, 0.5) 0%,
                rgba(252, 211, 77, 0.4) 20%, 
                rgba(252, 211, 77, 0.3) 40%,
                transparent 65%);
            pointer-events: none;
            z-index: 0;
            filter: blur(40px);
        }

        /* Main Content */
        .main-wrapper {
            margin-left: 0;
            margin-top: 80px;
            transition: margin-left 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .main-wrapper.sidebar-open {
            margin-left: 280px;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 2.5rem;
            position: relative;
            z-index: 1;
        }

        .banner-section {
            width: 100%;
            height: 320px;
            background: rgba(255, 255, 255, 0.35);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border-radius: 32px;
            border: 1px solid rgba(255, 255, 255, 0.6);
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.08),
                        inset 0 1px 0 rgba(255, 255, 255, 0.9);
            margin-bottom: 2.5rem;
            overflow: hidden;
            position: relative;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .banner-image {
            width: 100%;
            height: 100%;
            object-fit: cover;
            object-position: center;
        }

        .live-indicator {
            position: absolute;
            top: 24px;
            right: 24px;
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px 20px;
            background: rgba(255, 255, 255, 0.5);
            backdrop-filter: blur(10px);
            border-radius: 24px;
            border: 1px solid rgba(255, 255, 255, 0.8);
            font-size: 0.875rem;
            color: rgba(0, 0, 0, 0.7);
            font-weight: 600;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.06);
        }

        .live-dot {
            width: 10px;
            height: 10px;
            background: #10b981;
            border-radius: 50%;
            box-shadow: 0 0 12px rgba(16, 185, 129, 0.6);
            animation: livePulse 2s ease-in-out infinite;
        }

        .live-dot.updating {
            background: #f59e0b;
            box-shadow: 0 0 12px rgba(245, 158, 11, 0.6);
            animation: updatePulse 0.8s ease-in-out infinite;
        }

        @keyframes livePulse {
            0%, 100% {
                opacity: 1;
                transform: scale(1);
            }
            50% {
                opacity: 0.6;
                transform: scale(0.85);
            }
        }

        @keyframes updatePulse {
            0%, 100% {
                opacity: 1;
                transform: scale(1);
            }
            50% {
                opacity: 0.7;
                transform: scale(1.2);
            }
        }

        .main-content {
            display: grid;
            grid-template-columns: 420px 1fr;
            gap: 2.5rem;
            align-items: start;
        }

        .profile-section {
            background: rgba(255, 255, 255, 0.4);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border-radius: 32px;
            border: 1px solid rgba(255, 255, 255, 0.7);
            padding: 3rem;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.08),
                        inset 0 1px 0 rgba(255, 255, 255, 0.9);
            position: relative;
            display: flex;
            flex-direction: column;
        }

        .profile-picture-container {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 1.5rem;
            margin-bottom: 2.5rem;
            position: relative;
        }

        .profile-picture {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            border: 3px solid rgba(255, 255, 255, 0.9);
            background: linear-gradient(135deg, rgba(255, 237, 160, 0.5), rgba(254, 249, 195, 0.4));
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 3.5rem;
            color: rgba(0, 0, 0, 0.65);
            font-weight: 700;
            box-shadow: 0 12px 32px rgba(0, 0, 0, 0.12);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            overflow: hidden;
            position: relative;
            cursor: pointer;
        }

        .profile-picture:hover {
            transform: scale(1.05);
            box-shadow: 0 16px 48px rgba(0, 0, 0, 0.16);
        }

        .profile-picture img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .profile-picture:hover::after {
            content: '📷 Change';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.6);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1rem;
            color: white;
            font-weight: 600;
        }

        #profilePictureInput {
            display: none;
        }

        .profile-name {
            font-size: 1.75rem;
            font-weight: 700;
            color: rgba(0, 0, 0, 0.85);
            text-align: center;
            letter-spacing: -0.02em;
        }

        .profile-details {
            display: flex;
            flex-direction: column;
            gap: 1.25rem;
        }

        .detail-field {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        .detail-label {
            font-size: 0.75rem;
            color: rgba(0, 0, 0, 0.5);
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1.2px;
        }

        .detail-value {
            background: rgba(255, 255, 255, 0.65);
            backdrop-filter: blur(10px);
            padding: 1rem 1.5rem;
            border-radius: 16px;
            border: 1px solid rgba(255, 255, 255, 0.9);
            font-size: 1rem;
            color: rgba(0, 0, 0, 0.85);
            font-weight: 500;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
        }

        .dashboard-section {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 2rem;
        }

        .dashboard-card {
            background: rgba(255, 255, 255, 0.4);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border-radius: 28px;
            border: 1px solid rgba(255, 255, 255, 0.7);
            padding: 2.5rem;
            cursor: pointer;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            text-decoration: none;
            color: inherit;
            display: flex;
            flex-direction: column;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.08),
                        inset 0 1px 0 rgba(255, 255, 255, 0.9);
            position: relative;
            overflow: hidden;
            min-height: 240px;
        }

        .dashboard-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: radial-gradient(circle at top left, rgba(255, 237, 160, 0.15), transparent 70%);
            opacity: 0;
            transition: opacity 0.4s ease;
        }

        .dashboard-card:hover {
            transform: translateY(-12px);
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.12),
                        inset 0 1px 0 rgba(255, 255, 255, 1);
            border-color: rgba(255, 237, 160, 0.8);
        }

        .dashboard-card:hover::before {
            opacity: 1;
        }

        .dashboard-card.donation-card {
            border-color: rgba(59, 130, 246, 0.3);
        }

        .dashboard-card.donation-card:hover {
            border-color: rgba(59, 130, 246, 0.6);
        }

        .dashboard-card.donation-card::before {
            background: radial-gradient(circle at top left, rgba(59, 130, 246, 0.1), transparent 70%);
        }

        .card-title {
            font-size: 1.25rem;
            font-weight: 700;
            color: rgba(0, 0, 0, 0.85);
            margin-bottom: 1rem;
            letter-spacing: -0.01em;
        }

        .card-description {
            font-size: 0.9rem;
            color: rgba(0, 0, 0, 0.6);
            line-height: 1.6;
            margin-bottom: auto;
            font-weight: 400;
        }

        .card-stats {
            padding-top: 1.5rem;
            margin-top: 1.5rem;
            border-top: 1px solid rgba(0, 0, 0, 0.08);
            font-size: 2.5rem;
            color: rgba(0, 0, 0, 0.85);
            font-weight: 800;
            letter-spacing: -0.02em;
        }

        .dashboard-card.donation-card .card-stats {
            color: #3b82f6;
        }

        .card-stats-label {
            font-size: 0.75rem;
            color: rgba(0, 0, 0, 0.5);
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
            display: block;
            margin-bottom: 0.5rem;
        }

        .count-number {
            transition: all 0.3s ease;
        }

        .count-number.updating {
            color: #f59e0b;
            transform: scale(1.1);
        }

        /* Upload Toast Notification */
        .upload-toast {
            position: fixed;
            top: 2rem;
            right: 2rem;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(254, 240, 138, 0.5);
            border-radius: 16px;
            padding: 1.25rem 1.75rem;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.12);
            z-index: 1000;
            transform: translateX(400px);
            transition: transform 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .upload-toast.show {
            transform: translateX(0);
        }

        .upload-toast.success {
            border-color: rgba(16, 185, 129, 0.5);
        }

        .upload-toast.error {
            border-color: rgba(239, 68, 68, 0.5);
        }

        .toast-icon {
            font-size: 1.5rem;
        }

        .toast-message {
            font-size: 0.95rem;
            font-weight: 600;
            color: rgba(0, 0, 0, 0.85);
        }

        @media (max-width: 1200px) {
            .main-content {
                grid-template-columns: 1fr;
            }

            .profile-section {
                max-width: 600px;
                margin: 0 auto;
            }

            .main-wrapper.sidebar-open {
                margin-left: 0;
            }
        }

        @media (max-width: 768px) {
            .container {
                padding: 1.5rem;
            }

            .banner-section {
                height: 220px;
                border-radius: 24px;
            }

            .dashboard-section {
                grid-template-columns: 1fr;
            }

            .main-content {
                gap: 2rem;
            }

            .profile-picture {
                width: 120px;
                height: 120px;
                font-size: 2.5rem;
            }

            .card-stats {
                font-size: 2rem;
            }

            .upload-toast {
                top: 1rem;
                right: 1rem;
                left: 1rem;
                max-width: calc(100% - 2rem);
            }
        }
    </style>
</head>
<body>
    <?php 
    include __DIR__ . '/../components/header.php';
    include __DIR__ . '/../components/sidebar.php';
    ?>

    <!-- Main Wrapper -->
    <div class="main-wrapper" id="mainWrapper">
        <div class="container">
            <div class="banner-section">
                <img src="image.png" alt="Sponsor Dashboard" class="banner-image">
                <div class="live-indicator">
                    <div class="live-dot" id="live-dot"></div>
                    <span id="live-status">Live</span>
                </div>
            </div>

            <div class="main-content">
                <div class="profile-section">
                    <div class="profile-picture-container">
                        <div class="profile-picture" onclick="document.getElementById('profilePictureInput').click()">
                            <?php if (!empty($user_data['profile_picture']) && file_exists($user_data['profile_picture'])): ?>
                                <img src="<?php echo htmlspecialchars($user_data['profile_picture']); ?>" alt="Profile Picture" id="profilePictureImg">
                            <?php else: ?>
                                <span id="profileInitials"><?php echo $initials; ?></span>
                            <?php endif; ?>
                        </div>
                        <input type="file" id="profilePictureInput" accept="image/jpeg,image/jpg,image/png">
                        <div class="profile-name">
                            <?php echo htmlspecialchars($user_data['first_name'] . ' ' . $user_data['last_name']); ?>
                        </div>
                    </div>

                    <div class="profile-details">
                        <div class="detail-field">
                            <label class="detail-label">Full Name</label>
                            <div class="detail-value">
                                <?php echo htmlspecialchars($user_data['first_name'] . ' ' . $user_data['last_name']); ?>
                            </div>
                        </div>

                        <div class="detail-field">
                            <label class="detail-label">Email Address</label>
                            <div class="detail-value">
                                <?php echo htmlspecialchars($user_data['email']); ?>
                            </div>
                        </div>

                        <div class="detail-field">
                            <label class="detail-label">Phone Number</label>
                            <div class="detail-value">
                                <?php echo htmlspecialchars($user_data['phone_no']); ?>
                            </div>
                        </div>

                        <div class="detail-field">
                            <label class="detail-label">Member Since</label>
                            <div class="detail-value">
                                <?php echo date('F j, Y', strtotime($user_data['created_at'])); ?>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="dashboard-section">
                    <div class="dashboard-card" onclick="openDashboard('sponsored-children')">
                        <h3 class="card-title">Sponsored Children</h3>
                        <p class="card-description">View and manage all the children you are currently sponsoring</p>
                        <div class="card-stats">
                            <span class="card-stats-label">Total Sponsored</span>
                            <div class="count-number" id="sponsored-children-count"><?php echo $sponsored_children; ?></div>
                        </div>
                    </div>

                    <div class="dashboard-card donation-card" onclick="openDashboard('donation-history')">
                        <h3 class="card-title">Donation History</h3>
                        <p class="card-description">Track all your donations and payment records</p>
                        <div class="card-stats">
                            <span class="card-stats-label">Total Donated</span>
                            <div class="count-number" id="total-donations-count">₹<?php echo number_format($total_donated, 2); ?></div>
                        </div>
                    </div>

                    <div class="dashboard-card" onclick="openDashboard('reports-updates')">
                        <h3 class="card-title">Reports & Updates</h3>
                        <p class="card-description">Read progress reports and updates about sponsored children</p>
                        <div class="card-stats">
                            <span class="card-stats-label">New Reports</span>
                            <div class="count-number" id="reports-count"><?php echo $new_reports; ?></div>
                        </div>
                    </div>

                    <div class="dashboard-card" onclick="openDashboard('timeline')">
                        <h3 class="card-title">Timeline</h3>
                        <p class="card-description">View your sponsorship journey and important milestones</p>
                        <div class="card-stats">
                            <span class="card-stats-label">Recent Events</span>
                            <div class="count-number" id="timeline-events-count"><?php echo $recent_events; ?></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Upload Toast Notification -->
    <div class="upload-toast" id="uploadToast">
        <span class="toast-icon" id="toastIcon"></span>
        <span class="toast-message" id="toastMessage"></span>
    </div>

    <?php 
    include __DIR__ . '/../components/common_scripts.php';
    ?>

    <script>
        window.SPONSOR_ID = <?php echo $sponsor_id; ?>;

        document.getElementById('profilePictureInput').addEventListener('change', async function(e) {
            const file = e.target.files[0];
            if (!file) return;

            const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png'];
            if (!allowedTypes.includes(file.type)) {
                showToast('error', 'Please select a JPG, JPEG, or PNG image');
                return;
            }

            if (file.size > 5 * 1024 * 1024) {
                showToast('error', 'File size must be less than 5MB');
                return;
            }

            const formData = new FormData();
            formData.append('profile_picture', file);

            try {
                showToast('info', 'Uploading...');
                
                const response = await fetch('upload_profile_picture.php', {
                    method: 'POST',
                    body: formData
                });

                const result = await response.json();

                if (result.success) {
                    const profilePicture = document.querySelector('.profile-picture');
                    const existingImg = document.getElementById('profilePictureImg');
                    const initials = document.getElementById('profileInitials');
                    
                    if (existingImg) {
                        existingImg.src = result.image_path + '?t=' + new Date().getTime();
                    } else if (initials) {
                        initials.remove();
                        profilePicture.innerHTML = `<img src="${result.image_path}?t=${new Date().getTime()}" alt="Profile Picture" id="profilePictureImg">`;
                    }
                    
                    showToast('success', 'Profile picture updated successfully!');
                } else {
                    showToast('error', result.message || 'Upload failed');
                }
            } catch (error) {
                console.error('Upload error:', error);
                showToast('error', 'An error occurred during upload');
            }
        });

        function showToast(type, message) {
            const toast = document.getElementById('uploadToast');
            const icon = document.getElementById('toastIcon');
            const msg = document.getElementById('toastMessage');

            const icons = { success: '✅', error: '❌', info: '⏳' };
            icon.textContent = icons[type] || '📢';
            msg.textContent = message;

            toast.classList.remove('success', 'error', 'info');
            if (type !== 'info') toast.classList.add(type);

            toast.classList.add('show');
            setTimeout(() => toast.classList.remove('show'), 3000);
        }

        function openDashboard(type) {
            const routes = {
                'sponsored-children': 'sponsored_children.php',
                'donation-history': 'donation_history.php',
                'reports-updates': 'reports_updates.php',
                'timeline': 'timeline.php'
            };
            window.location.href = routes[type];
        }

        function refreshDashboardCounts() {
            const liveDot = document.getElementById('live-dot');
            const liveStatus = document.getElementById('live-status');
            
            liveDot.classList.add('updating');
            liveStatus.textContent = 'Updating...';
            
            fetch(`get_dashboard_counts.php?sponsor_id=${window.SPONSOR_ID}`)
                .then(response => {
                    if (!response.ok) throw new Error('Network response was not ok');
                    return response.json();
                })
                .then(data => {
                    if (data.success) {
                        const currentSponsored = document.getElementById('sponsored-children-count').textContent.trim();
                        const currentDonated = document.getElementById('total-donations-count').textContent.replace(/[^0-9.]/g, '');
                        const currentReports = document.getElementById('reports-count').textContent.trim();
                        const currentEvents = document.getElementById('timeline-events-count').textContent.trim();
                        
                        const newSponsored = String(data.sponsored_children);
                        const newDonated = String(parseFloat(data.total_donated).toFixed(2));
                        const newReports = String(data.new_reports);
                        const newEvents = String(data.recent_events);
                        
                        if (currentSponsored !== newSponsored) {
                            updateCount('sponsored-children-count', newSponsored);
                        }
                        
                        if (currentDonated !== newDonated) {
                            const formatted = '₹' + parseFloat(data.total_donated).toLocaleString('en-IN', {
                                minimumFractionDigits: 2,
                                maximumFractionDigits: 2
                            });
                            updateCount('total-donations-count', formatted);
                        }
                        
                        if (currentReports !== newReports) {
                            updateCount('reports-count', newReports);
                        }
                        
                        if (currentEvents !== newEvents) {
                            updateCount('timeline-events-count', newEvents);
                        }
                        
                        console.log('✅ Dashboard updated at:', data.timestamp);
                    }
                    
                    setTimeout(() => {
                        liveDot.classList.remove('updating');
                        liveStatus.textContent = 'Live';
                    }, 500);
                })
                .catch(error => {
                    console.error('❌ Error refreshing counts:', error);
                    liveDot.classList.remove('updating');
                    liveStatus.textContent = 'Error';
                    setTimeout(() => liveStatus.textContent = 'Live', 3000);
                });
        }

        function updateCount(elementId, newValue) {
            const element = document.getElementById(elementId);
            if (!element) return;
            
            const currentValue = element.textContent.trim();
            
            if (currentValue !== String(newValue)) {
                element.classList.add('updating');
                setTimeout(() => {
                    element.textContent = newValue;
                    setTimeout(() => element.classList.remove('updating'), 300);
                }, 150);
            }
        }

        document.addEventListener('DOMContentLoaded', function() {
            console.log('🚀 Dashboard initialized with sponsor ID:', window.SPONSOR_ID);
            
            const cards = document.querySelectorAll('.dashboard-card');
            cards.forEach((card, index) => {
                card.style.opacity = '0';
                card.style.transform = 'translateY(30px)';
                
                setTimeout(() => {
                    card.style.transition = 'all 0.6s cubic-bezier(0.4, 0, 0.2, 1)';
                    card.style.opacity = '1';
                    card.style.transform = 'translateY(0)';
                }, index * 100);
            });

            setTimeout(() => {
                console.log('⏰ Starting auto-refresh cycle...');
                refreshDashboardCounts();
                setInterval(refreshDashboardCounts, 30000);
            }, 5000);
            
            let isRefreshing = false;
            document.addEventListener('visibilitychange', function() {
                if (!document.hidden && !isRefreshing) {
                    isRefreshing = true;
                    console.log('👁️ Tab focused - refreshing counts...');
                    refreshDashboardCounts();
                    setTimeout(() => isRefreshing = false, 2000);
                }
            });
        });
    </script>
</body>
</html>