<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: signup_and_login/login_template.php");
    exit();
}

require_once __DIR__ . '/../db_config.php';
require_once __DIR__ . '/../components/sidebar_config.php';

if (!isset($conn)) {
    die("Database connection failed. Please check db_config.php");
}

$user_id = $_SESSION['user_id'];

// Get sponsor information
$query = "SELECT u.user_id, u.username, u.email, 
                 s.sponsor_id, s.first_name, s.last_name
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

// Get quick stats
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM sponsorships WHERE sponsor_id = ? AND (end_date IS NULL OR end_date > CURDATE())");
$stmt->bind_param("i", $sponsor_id);
$stmt->execute();
$sponsored_children = $stmt->get_result()->fetch_assoc()['count'];
$stmt->close();

// Get total donations
$stmt = $conn->prepare("SELECT COALESCE(SUM(amount), 0) as total FROM donations WHERE sponsor_id = ?");
$stmt->bind_param("i", $sponsor_id);
$stmt->execute();
$total_donated = $stmt->get_result()->fetch_assoc()['total'];
$stmt->close();

// Get sponsored children details
$query = "SELECT c.child_id, c.first_name, c.last_name, c.profile_picture, c.dob
          FROM children c
          INNER JOIN sponsorships sp ON c.child_id = sp.child_id
          WHERE sp.sponsor_id = ? AND (sp.end_date IS NULL OR sp.end_date > CURDATE())";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $sponsor_id);
$stmt->execute();
$children_result = $stmt->get_result();
$sponsored_children_list = $children_result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Get calendar month from URL or default to current
$year = isset($_GET['year']) ? intval($_GET['year']) : date('Y');
$month = isset($_GET['month']) ? intval($_GET['month']) : date('m');

// Validate month/year
if ($month < 1 || $month > 12) $month = date('m');
if ($year < 2020 || $year > 2100) $year = date('Y');

// Get calendar events for selected month
$first_day = sprintf('%04d-%02d-01', $year, $month);
$last_day = date('Y-m-t', strtotime($first_day));

$calendar_events = [];

// Get birthdays
foreach ($sponsored_children_list as $child) {
    $birthday_this_year = date('Y') . '-' . date('m-d', strtotime($child['dob']));
    if ($birthday_this_year >= $first_day && $birthday_this_year <= $last_day) {
        $calendar_events[] = [
            'type' => 'birthday',
            'date' => $birthday_this_year,
            'title' => $child['first_name'] . "'s Birthday",
            'child_name' => $child['first_name'] . ' ' . $child['last_name'],
            'icon' => '🎂'
        ];
    }
}

// Get achievements
foreach ($sponsored_children_list as $child) {
    $query = "SELECT upload_id, upload_date, file_path 
              FROM child_uploads 
              WHERE child_id = ? AND category = 'Achievement' 
              AND upload_date >= ? AND upload_date <= ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("iss", $child['child_id'], $first_day, $last_day);
    $stmt->execute();
    $achievements = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    
    foreach ($achievements as $ach) {
        $calendar_events[] = [
            'type' => 'achievement',
            'date' => $ach['upload_date'],
            'title' => 'Achievement',
            'child_name' => $child['first_name'] . ' ' . $child['last_name'],
            'icon' => '🏆'
        ];
    }
}

// Get reports
foreach ($sponsored_children_list as $child) {
    $query = "SELECT report_id, report_date, report_text 
              FROM child_reports 
              WHERE child_id = ? 
              AND report_date >= ? AND report_date <= ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("iss", $child['child_id'], $first_day, $last_day);
    $stmt->execute();
    $reports = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    
    foreach ($reports as $rep) {
        $calendar_events[] = [
            'type' => 'report',
            'date' => $rep['report_date'],
            'title' => 'Progress Report',
            'child_name' => $child['first_name'] . ' ' . $child['last_name'],
            'icon' => '📄'
        ];
    }
}

// Get public events
foreach ($sponsored_children_list as $child) {
    $query = "SELECT event_id, title, event_date, description 
              FROM calendar_events 
              WHERE child_id = ? AND is_public = 1 
              AND event_date >= ? AND event_date <= ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("iss", $child['child_id'], $first_day, $last_day);
    $stmt->execute();
    $events = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    
    foreach ($events as $evt) {
        $calendar_events[] = [
            'type' => 'event',
            'date' => $evt['event_date'],
            'title' => $evt['title'],
            'child_name' => $child['first_name'] . ' ' . $child['last_name'],
            'icon' => '🎉'
        ];
    }
}

$conn->close();

// Calculate prev/next month navigation
$prev_month = $month - 1;
$prev_year = $year;
if ($prev_month < 1) {
    $prev_month = 12;
    $prev_year--;
}

$next_month = $month + 1;
$next_year = $year;
if ($next_month > 12) {
    $next_month = 1;
    $next_year++;
}

// Initialize sidebar
$sidebar_menu = initSidebar('sponsor', 'my_home.php');
$logout_path = '../signup_and_login/logout.php';

// Helper function to generate calendar
function generateCalendar($events, $year, $month) {
    $first_day = sprintf('%04d-%02d-01', $year, $month);
    $last_day = date('Y-m-t', strtotime($first_day));
    $month_name = date('F Y', strtotime($first_day));
    $start_weekday = date('w', strtotime($first_day)); // 0 (Sun) - 6 (Sat)
    $days_in_month = date('t', strtotime($first_day));
    
    $events_by_date = [];
    foreach ($events as $event) {
        $date = $event['date'];
        if (!isset($events_by_date[$date])) {
            $events_by_date[$date] = [];
        }
        $events_by_date[$date][] = $event;
    }
    
    return [
        'month_name' => $month_name,
        'start_weekday' => $start_weekday,
        'days_in_month' => $days_in_month,
        'events_by_date' => $events_by_date
    ];
}

$calendar = generateCalendar($calendar_events, $year, $month);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Home - Sponsor Dashboard</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: #fafafa;
            min-height: 100vh;
        }

        .main-wrapper {
            margin-left: 0;
            margin-top: 80px;
            transition: margin-left 0.3s ease;
        }

        .main-wrapper.sidebar-open {
            margin-left: 280px;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 2rem;
        }

        /* Hero Section */
        .hero-section {
            background: linear-gradient(135deg, #fbbf24, #f59e0b);
            border-radius: 20px;
            padding: 3rem;
            text-align: center;
            color: white;
            margin-bottom: 2rem;
            box-shadow: 0 10px 30px rgba(251, 191, 36, 0.3);
        }

        .hero-title {
            font-size: 2.5rem;
            font-weight: 800;
            margin-bottom: 1rem;
        }

        .hero-subtitle {
            font-size: 1.25rem;
            margin-bottom: 2rem;
            opacity: 0.95;
        }

        .sponsor-button {
            display: inline-block;
            padding: 1rem 3rem;
            background: white;
            color: #f59e0b;
            font-size: 1.125rem;
            font-weight: 700;
            border-radius: 12px;
            text-decoration: none;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
        }

        .sponsor-button:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.3);
        }

        /* Stats Section */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: white;
            border-radius: 16px;
            padding: 2rem;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
            text-align: center;
            transition: transform 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-5px);
        }

        .stat-icon {
            font-size: 2.5rem;
            margin-bottom: 1rem;
        }

        .stat-number {
            font-size: 2.5rem;
            font-weight: 800;
            color: #1f2937;
            margin-bottom: 0.5rem;
        }

        .stat-label {
            font-size: 0.875rem;
            color: #6b7280;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        /* My Sponsors Section */
        .section-title {
            font-size: 1.75rem;
            font-weight: 700;
            color: #1f2937;
            margin-bottom: 1.5rem;
        }

        .children-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 1.5rem;
            margin-bottom: 3rem;
        }

        .child-card {
            background: white;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
            transition: all 0.3s ease;
            position: relative;
        }

        .child-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.12);
        }

        .child-image {
            width: 100%;
            height: 220px;
            object-fit: cover;
            background: #e5e7eb;
        }

        .child-info {
            padding: 1.5rem;
        }

        .child-name {
            font-size: 1.25rem;
            font-weight: 700;
            color: #1f2937;
            margin-bottom: 0.5rem;
        }

        .child-age {
            font-size: 0.875rem;
            color: #6b7280;
            margin-bottom: 1rem;
        }

        .download-icon {
            position: absolute;
            top: 1rem;
            right: 1rem;
            background: white;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
            transition: all 0.3s ease;
        }

        .download-icon:hover {
            transform: scale(1.1);
            background: #fbbf24;
            color: white;
        }

        /* Calendar Section */
        .calendar-section {
            background: white;
            border-radius: 16px;
            padding: 1.5rem;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
            max-width: 900px;
            margin: 0 auto;
        }

        .calendar-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid #e5e7eb;
        }

        .calendar-month {
            font-size: 1.25rem;
            font-weight: 700;
            color: #1f2937;
        }

        .calendar-nav {
            display: flex;
            gap: 0.5rem;
        }

        .nav-button {
            padding: 0.5rem 1rem;
            background: #f3f4f6;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s ease;
            text-decoration: none;
            color: #374151;
            font-size: 0.875rem;
        }

        .nav-button:hover {
            background: #e5e7eb;
        }

        .calendar-grid {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 0.5rem;
        }

        .calendar-day-header {
            padding: 0.75rem;
            text-align: center;
            font-weight: 600;
            color: #6b7280;
            font-size: 0.75rem;
            text-transform: uppercase;
        }

        .calendar-day {
            aspect-ratio: 1;
            padding: 0.5rem;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            position: relative;
            cursor: pointer;
            transition: all 0.3s ease;
            min-height: 70px;
            display: flex;
            flex-direction: column;
        }

        .calendar-day:hover {
            background: #fef3c7;
            border-color: #fbbf24;
        }

        .calendar-day.empty {
            border: none;
            cursor: default;
        }

        .calendar-day.empty:hover {
            background: transparent;
        }

        .calendar-day.today {
            border-color: #fbbf24;
            border-width: 2px;
            background: #fffbeb;
        }

        .day-number {
            font-weight: 600;
            color: #1f2937;
            margin-bottom: 0.25rem;
            font-size: 0.875rem;
        }

        .day-events {
            display: flex;
            flex-wrap: wrap;
            gap: 3px;
            margin-top: 0.25rem;
        }

        .event-dot {
            width: 6px;
            height: 6px;
            border-radius: 50%;
            display: inline-block;
        }

        .event-dot.birthday { background: #ec4899; }
        .event-dot.achievement { background: #10b981; }
        .event-dot.report { background: #3b82f6; }
        .event-dot.event { background: #8b5cf6; }

        /* Event Modal */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }

        .modal.active {
            display: flex;
        }

        .modal-content {
            background: white;
            border-radius: 16px;
            padding: 2rem;
            max-width: 500px;
            width: 90%;
            max-height: 80vh;
            overflow-y: auto;
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }

        .modal-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: #1f2937;
        }

        .close-button {
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: #6b7280;
        }

        .event-list {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

        .event-item {
            padding: 1rem;
            background: #f9fafb;
            border-radius: 8px;
            border-left: 4px solid #fbbf24;
        }

        .event-title {
            font-weight: 600;
            color: #1f2937;
            margin-bottom: 0.25rem;
        }

        .event-child {
            font-size: 0.875rem;
            color: #6b7280;
        }

        @media (max-width: 1200px) {
            .main-wrapper.sidebar-open {
                margin-left: 0;
            }
        }

        @media (max-width: 768px) {
            .hero-title {
                font-size: 2rem;
            }

            .calendar-section {
                padding: 1rem;
            }

            .calendar-day {
                min-height: 50px;
                padding: 0.25rem;
            }

            .day-number {
                font-size: 0.75rem;
            }

            .calendar-day-header {
                padding: 0.5rem;
                font-size: 0.625rem;
            }

            .event-dot {
                width: 4px;
                height: 4px;
            }
        }
    </style>
</head>
<body>
    <?php 
    include __DIR__ . '/../components/header.php';
    include __DIR__ . '/../components/sidebar.php';
    ?>

    <div class="main-wrapper" id="mainWrapper">
        <div class="container">
            <!-- Hero Section -->
            <div class="hero-section">
                <h1 class="hero-title">Welcome, <?php echo htmlspecialchars($user_data['first_name']); ?>! 👋</h1>
                <p class="hero-subtitle">Make a difference in a child's life today</p>
                <a href="../all_children_profiles_sponser.php" class="sponsor-button">
                    Sponsor a Child Now ✨
                </a>
            </div>

            <!-- Stats Section -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon">👧👦</div>
                    <div class="stat-number"><?php echo $sponsored_children; ?></div>
                    <div class="stat-label">Children Sponsored</div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon">💰</div>
                    <div class="stat-number">₹<?php echo number_format($total_donated, 0); ?></div>
                    <div class="stat-label">Amount Donated</div>
                </div>
            </div>

            <!-- My Sponsors Section -->
            <h2 class="section-title">My Sponsored Children</h2>
            <div class="children-grid">
                <?php if (empty($sponsored_children_list)): ?>
                    <p style="grid-column: 1/-1; text-align: center; color: #6b7280;">
                        You haven't sponsored any children yet. Start making a difference today!
                    </p>
                <?php else: ?>
                    <?php foreach ($sponsored_children_list as $child): 
                        $age = date_diff(date_create($child['dob']), date_create('today'))->y;
                    ?>
                        <div class="child-card">
                            <div class="download-icon" title="Download Profile">
                                📥
                            </div>
                            <?php if ($child['profile_picture']): ?>
                                <img src="../<?php echo htmlspecialchars($child['profile_picture']); ?>" 
                                     alt="<?php echo htmlspecialchars($child['first_name']); ?>" 
                                     class="child-image">
                            <?php else: ?>
                                <div class="child-image" style="display: flex; align-items: center; justify-content: center; font-size: 4rem;">
                                    👤
                                </div>
                            <?php endif; ?>
                            <div class="child-info">
                                <div class="child-name"><?php echo htmlspecialchars($child['first_name'] . ' ' . $child['last_name']); ?></div>
                                <div class="child-age"><?php echo $age; ?> years old</div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- Calendar Section -->
            <h2 class="section-title">Upcoming Events</h2>
            <div class="calendar-section">
                <div class="calendar-header">
                    <div class="calendar-month"><?php echo $calendar['month_name']; ?></div>
                    <div class="calendar-nav">
                        <a href="?year=<?php echo $prev_year; ?>&month=<?php echo $prev_month; ?>" class="nav-button">← Prev</a>
                        <a href="?year=<?php echo date('Y'); ?>&month=<?php echo date('m'); ?>" class="nav-button">Today</a>
                        <a href="?year=<?php echo $next_year; ?>&month=<?php echo $next_month; ?>" class="nav-button">Next →</a>
                    </div>
                </div>

                <div class="calendar-grid">
                    <!-- Day Headers -->
                    <div class="calendar-day-header">Sun</div>
                    <div class="calendar-day-header">Mon</div>
                    <div class="calendar-day-header">Tue</div>
                    <div class="calendar-day-header">Wed</div>
                    <div class="calendar-day-header">Thu</div>
                    <div class="calendar-day-header">Fri</div>
                    <div class="calendar-day-header">Sat</div>

                    <!-- Empty cells before month starts -->
                    <?php for ($i = 0; $i < $calendar['start_weekday']; $i++): ?>
                        <div class="calendar-day empty"></div>
                    <?php endfor; ?>

                    <!-- Days of the month -->
                    <?php 
                    $today = date('Y-m-d');
                    for ($day = 1; $day <= $calendar['days_in_month']; $day++): 
                        $date = sprintf('%04d-%02d-%02d', $year, $month, $day);
                        $day_events = isset($calendar['events_by_date'][$date]) ? $calendar['events_by_date'][$date] : [];
                        $is_today = ($date === $today);
                    ?>
                        <div class="calendar-day <?php echo $is_today ? 'today' : ''; ?>" 
                             data-date="<?php echo $date; ?>" 
                             onclick="showEvents('<?php echo $date; ?>')">
                            <div class="day-number"><?php echo $day; ?></div>
                            <?php if (!empty($day_events)): ?>
                                <div class="day-events">
                                    <?php foreach ($day_events as $event): ?>
                                        <span class="event-dot <?php echo $event['type']; ?>" title="<?php echo htmlspecialchars($event['title']); ?>"></span>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endfor; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Event Modal -->
    <div class="modal" id="eventModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title" id="modalTitle">Events</h3>
                <button class="close-button" onclick="closeModal()">×</button>
            </div>
            <div class="event-list" id="eventList">
                <!-- Events will be populated by JavaScript -->
            </div>
        </div>
    </div>

    <?php include __DIR__ . '/../components/common_scripts.php'; ?>

    <script>
        // Store events data
        const eventsData = <?php echo json_encode($calendar['events_by_date']); ?>;

        function showEvents(date) {
            const events = eventsData[date];
            if (!events || events.length === 0) return;

            const modal = document.getElementById('eventModal');
            const modalTitle = document.getElementById('modalTitle');
            const eventList = document.getElementById('eventList');

            // Format date
            const dateObj = new Date(date + 'T00:00:00');
            const options = { month: 'long', day: 'numeric', year: 'numeric' };
            modalTitle.textContent = dateObj.toLocaleDateString('en-US', options);

            // Build event list
            eventList.innerHTML = events.map(event => `
                <div class="event-item">
                    <div class="event-title">${event.icon} ${event.title}</div>
                    <div class="event-child">${event.child_name}</div>
                </div>
            `).join('');

            modal.classList.add('active');
        }

        function closeModal() {
            document.getElementById('eventModal').classList.remove('active');
        }

        // Close modal on outside click
        document.getElementById('eventModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeModal();
            }
        });
    </script>
</body>
</html>