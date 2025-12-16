<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: ../signup_and_login/login_template.php");
    exit();
}

require_once __DIR__ . '/../db_config.php';
require_once __DIR__ . '/../components/sidebar_config.php';
require_once __DIR__ . '/../includes/calendar_functions.php';

// Check if user is staff/admin/owner
$user_role = $_SESSION['user_role'] ?? $_SESSION['role'] ?? '';

// Normalize role to lowercase for comparison
$user_role_lower = strtolower($user_role);

if (!in_array($user_role_lower, ['staff', 'admin', 'owner'])) {
    // If access_denied.php doesn't exist, redirect to appropriate page
    if (file_exists(__DIR__ . '/../access_denied.php')) {
        header("Location: ../access_denied.php");
    } else {
        // Fallback: redirect to login
        $_SESSION['error'] = "You don't have permission to access this page.";
        header("Location: ../signup_and_login/login_template.php");
    }
    exit();
}

$user_id = $_SESSION['user_id'];

// Get current month/year or from query params
$year = isset($_GET['year']) ? intval($_GET['year']) : date('Y');
$month = isset($_GET['month']) ? intval($_GET['month']) : date('m');

// Validate month/year
if ($month < 1 || $month > 12) $month = date('m');
if ($year < 2020 || $year > 2100) $year = date('Y');

// Generate calendar structure
$calendar = generateCalendarGrid($year, $month);

// Get all events for this month
$events = getStaffCalendarEvents($conn, $calendar['first_day'], $calendar['last_day']);
$events_by_date = groupEventsByDate($events);

// Get all children for add event modal
$children_for_modal = [];
$children_query = "SELECT child_id, first_name, last_name, profile_picture FROM children ORDER BY first_name, last_name";
$children_result = $conn->query($children_query);
if ($children_result) {
    $children_for_modal = $children_result->fetch_all(MYSQLI_ASSOC);
}

// Calculate prev/next month
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
$sidebar_menu = initSidebar('staff', 'staff_calendar.php');
$logout_path = '../signup_and_login/logout.php';

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Calendar - Staff Dashboard</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: #f9fafb;
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
            max-width: 1600px;
            margin: 0 auto;
            padding: 2rem;
        }

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
        }

        .page-title {
            font-size: 2rem;
            font-weight: 700;
            color: #1f2937;
        }

        .add-event-btn {
            padding: 0.75rem 1.5rem;
            background: linear-gradient(135deg, #fbbf24, #f59e0b);
            color: white;
            font-weight: 600;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
        }

        .add-event-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(251, 191, 36, 0.4);
        }

        .calendar-section {
            background: white;
            border-radius: 16px;
            padding: 2rem;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
        }

        .calendar-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid #e5e7eb;
        }

        .calendar-month {
            font-size: 1.75rem;
            font-weight: 700;
            color: #1f2937;
        }

        .calendar-nav {
            display: flex;
            gap: 0.5rem;
        }

        .nav-button {
            padding: 0.5rem 1.25rem;
            background: #f3f4f6;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            color: #374151;
            transition: all 0.3s ease;
            text-decoration: none;
        }

        .nav-button:hover {
            background: #e5e7eb;
        }

        .legend {
            display: flex;
            gap: 1.5rem;
            flex-wrap: wrap;
            margin-bottom: 1.5rem;
        }

        .legend-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.875rem;
            color: #6b7280;
        }

        .legend-dot {
            width: 12px;
            height: 12px;
            border-radius: 50%;
        }

        .calendar-grid {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 0.5rem;
        }

        .calendar-day-header {
            padding: 1rem;
            text-align: center;
            font-weight: 700;
            color: #374151;
            font-size: 0.875rem;
            text-transform: uppercase;
            background: #f9fafb;
            border-radius: 8px;
        }

        .calendar-day {
            min-height: 120px;
            padding: 0.75rem;
            border: 2px solid #e5e7eb;
            border-radius: 10px;
            position: relative;
            cursor: pointer;
            transition: all 0.3s ease;
            background: white;
        }

        .calendar-day:hover {
            border-color: #fbbf24;
            box-shadow: 0 4px 12px rgba(251, 191, 36, 0.2);
        }

        .calendar-day.empty {
            border: none;
            background: transparent;
            cursor: default;
        }

        .calendar-day.empty:hover {
            box-shadow: none;
        }

        .calendar-day.today {
            border-color: #fbbf24;
            background: #fffbeb;
        }

        .day-number {
            font-weight: 700;
            color: #1f2937;
            font-size: 1rem;
            margin-bottom: 0.5rem;
        }

        .day-events {
            display: flex;
            flex-direction: column;
            gap: 3px;
            max-height: 80px;
            overflow-y: auto;
        }

        .event-badge {
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            font-size: 0.75rem;
            font-weight: 600;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            display: flex;
            align-items: center;
            gap: 0.25rem;
        }

        .event-badge.birthday { background: #fce7f3; color: #be185d; }
        .event-badge.achievement { background: #d1fae5; color: #065f46; }
        .event-badge.report { background: #dbeafe; color: #1e40af; }
        .event-badge.event { background: #ede9fe; color: #5b21b6; }
        .event-badge.internal { background: #fee2e2; color: #991b1b; }

        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.6);
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
            max-width: 600px;
            width: 90%;
            max-height: 80vh;
            overflow-y: auto;
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid #e5e7eb;
        }

        .modal-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: #1f2937;
        }

        .close-button {
            background: none;
            border: none;
            font-size: 2rem;
            cursor: pointer;
            color: #6b7280;
            line-height: 1;
            padding: 0;
            width: 32px;
            height: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .close-button:hover {
            color: #1f2937;
        }

        .event-list {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

        .event-item {
            padding: 1.25rem;
            background: #f9fafb;
            border-radius: 10px;
            border-left: 4px solid;
        }

        .event-item.birthday { border-color: #ec4899; }
        .event-item.achievement { border-color: #10b981; }
        .event-item.report { border-color: #3b82f6; }
        .event-item.event { border-color: #8b5cf6; }
        .event-item.internal { border-color: #ef4444; }

        .event-header {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            margin-bottom: 0.5rem;
        }

        .event-icon {
            font-size: 1.5rem;
        }

        .event-title {
            font-weight: 700;
            color: #1f2937;
            font-size: 1.125rem;
        }

        .event-child {
            font-size: 0.875rem;
            color: #6b7280;
            margin-bottom: 0.5rem;
        }

        .event-description {
            font-size: 0.875rem;
            color: #374151;
            line-height: 1.5;
        }

        .event-badge-inline {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 6px;
            font-size: 0.75rem;
            font-weight: 600;
            margin-top: 0.5rem;
        }

        .event-badge-inline.public { background: #dbeafe; color: #1e40af; }
        .event-badge-inline.internal { background: #fee2e2; color: #991b1b; }

        @media (max-width: 1200px) {
            .main-wrapper.sidebar-open {
                margin-left: 0;
            }
        }

        @media (max-width: 768px) {
            .page-header {
                flex-direction: column;
                gap: 1rem;
                align-items: stretch;
            }

            .calendar-day {
                min-height: 80px;
                padding: 0.5rem;
            }

            .day-number {
                font-size: 0.875rem;
            }

            .event-badge {
                font-size: 0.625rem;
                padding: 0.125rem 0.25rem;
            }

            .legend {
                font-size: 0.75rem;
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
            <?php if (isset($_SESSION['success_message'])): ?>
                <div style="background: #d1fae5; border: 1px solid #34d399; color: #065f46; padding: 1rem 1.5rem; border-radius: 12px; margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.75rem; font-weight: 500;">
                    <span style="font-size: 1.25rem;">✓</span>
                    <span><?php echo htmlspecialchars($_SESSION['success_message']); ?></span>
                </div>
                <?php unset($_SESSION['success_message']); ?>
            <?php endif; ?>

            <div class="page-header">
                <h1 class="page-title">📅 Calendar</h1>
                <button onclick="showAddEventModal()" class="add-event-btn">+ Add Event</button>
            </div>

            <div class="calendar-section">
                <div class="calendar-header">
                    <div class="calendar-month"><?php echo $calendar['month_name']; ?></div>
                    <div class="calendar-nav">
                        <a href="?year=<?php echo $prev_year; ?>&month=<?php echo $prev_month; ?>" class="nav-button">← Prev</a>
                        <a href="?year=<?php echo date('Y'); ?>&month=<?php echo date('m'); ?>" class="nav-button">Today</a>
                        <a href="?year=<?php echo $next_year; ?>&month=<?php echo $next_month; ?>" class="nav-button">Next →</a>
                    </div>
                </div>

                <div class="legend">
                    <div class="legend-item">
                        <span class="legend-dot" style="background: #ec4899;"></span>
                        <span>Birthday</span>
                    </div>
                    <div class="legend-item">
                        <span class="legend-dot" style="background: #10b981;"></span>
                        <span>Achievement</span>
                    </div>
                    <div class="legend-item">
                        <span class="legend-dot" style="background: #3b82f6;"></span>
                        <span>Report</span>
                    </div>
                    <div class="legend-item">
                        <span class="legend-dot" style="background: #8b5cf6;"></span>
                        <span>Public Event</span>
                    </div>
                    <div class="legend-item">
                        <span class="legend-dot" style="background: #ef4444;"></span>
                        <span>Internal Event</span>
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
                        $date = sprintf('%s-%02d', $calendar['year'] . '-' . sprintf('%02d', $calendar['month']), $day);
                        $day_events = isset($events_by_date[$date]) ? $events_by_date[$date] : [];
                        $is_today = ($date === $today);
                    ?>
                        <div class="calendar-day <?php echo $is_today ? 'today' : ''; ?>" 
                             data-date="<?php echo $date; ?>" 
                             onclick="showEvents('<?php echo $date; ?>')">
                            <div class="day-number"><?php echo $day; ?></div>
                            <?php if (!empty($day_events)): ?>
                                <div class="day-events">
                                    <?php foreach (array_slice($day_events, 0, 3) as $event): ?>
                                        <div class="event-badge <?php echo $event['type']; ?>">
                                            <?php echo $event['icon']; ?> <?php echo htmlspecialchars($event['title']); ?>
                                        </div>
                                    <?php endforeach; ?>
                                    <?php if (count($day_events) > 3): ?>
                                        <div class="event-badge" style="background: #f3f4f6; color: #6b7280;">
                                            +<?php echo count($day_events) - 3; ?> more
                                        </div>
                                    <?php endif; ?>
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
                <!-- Events populated by JavaScript -->
            </div>
        </div>
    </div>

    <!-- Add Event Modal -->
    <div class="modal" id="addEventModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Select Child for New Event</h3>
                <button class="close-button" onclick="closeAddEventModal()">×</button>
            </div>
            <div style="padding: 1rem;">
                <input 
                    type="text" 
                    id="childSearch" 
                    placeholder="🔍 Search for a child by name..." 
                    style="width: 100%; padding: 0.75rem 1rem; border: 2px solid #e5e7eb; border-radius: 10px; font-size: 1rem; margin-bottom: 1rem; transition: all 0.3s ease;"
                    onkeyup="filterChildren()"
                    onfocus="this.style.borderColor='#fbbf24'; this.style.boxShadow='0 0 0 3px rgba(251, 191, 36, 0.1)'"
                    onblur="this.style.borderColor='#e5e7eb'; this.style.boxShadow='none'"
                >
                <p style="color: #6b7280; margin-bottom: 1rem; font-size: 0.875rem;" id="resultCount">
                    Showing <?php echo count($children_for_modal); ?> children
                </p>
                <div id="childList" style="display: flex; flex-direction: column; gap: 0.75rem; max-height: 400px; overflow-y: auto;">
                    <?php foreach ($children_for_modal as $child): ?>
                        <a href="staff_add_event.php?child_id=<?php echo $child['child_id']; ?>" 
                           class="child-item"
                           data-child-name="<?php echo strtolower($child['first_name'] . ' ' . $child['last_name']); ?>"
                           style="display: flex; align-items: center; gap: 1rem; padding: 1rem; background: #f9fafb; border-radius: 10px; text-decoration: none; color: #1f2937; transition: all 0.3s ease;">
                            <?php if ($child['profile_picture']): ?>
                                <img src="../<?php echo htmlspecialchars($child['profile_picture']); ?>" 
                                     style="width: 48px; height: 48px; border-radius: 50%; object-fit: cover;">
                            <?php else: ?>
                                <div style="width: 48px; height: 48px; border-radius: 50%; background: #e5e7eb; display: flex; align-items: center; justify-content: center; font-size: 1.5rem;">
                                    👤
                                </div>
                            <?php endif; ?>
                            <div style="flex: 1;">
                                <div style="font-weight: 600; color: #1f2937;">
                                    <?php echo htmlspecialchars($child['first_name'] . ' ' . $child['last_name']); ?>
                                </div>
                            </div>
                            <span style="color: #fbbf24;">→</span>
                        </a>
                    <?php endforeach; ?>
                </div>
                <p id="noResults" style="display: none; text-align: center; color: #9ca3af; padding: 2rem; font-style: italic;">
                    No children found matching your search.
                </p>
            </div>
        </div>
    </div>

    <?php include __DIR__ . '/../components/common_scripts.php'; ?>

    <script>
        const eventsData = <?php echo json_encode($events_by_date); ?>;

        function showEvents(date) {
            const events = eventsData[date];
            if (!events || events.length === 0) return;

            const modal = document.getElementById('eventModal');
            const modalTitle = document.getElementById('modalTitle');
            const eventList = document.getElementById('eventList');

            const dateObj = new Date(date + 'T00:00:00');
            const options = { weekday: 'long', month: 'long', day: 'numeric', year: 'numeric' };
            modalTitle.textContent = dateObj.toLocaleDateString('en-US', options);

            eventList.innerHTML = events.map(event => {
                let visibilityBadge = '';
                if (event.type === 'event' || event.type === 'internal') {
                    visibilityBadge = `<span class="event-badge-inline ${event.is_public ? 'public' : 'internal'}">
                        ${event.is_public ? '👁️ Visible to Sponsors' : '🔒 Staff Only'}
                    </span>`;
                }

                return `
                    <div class="event-item ${event.type}">
                        <div class="event-header">
                            <span class="event-icon">${event.icon}</span>
                            <span class="event-title">${event.title}</span>
                        </div>
                        <div class="event-child">👤 ${event.child_name}</div>
                        ${event.description ? `<div class="event-description">${event.description}</div>` : ''}
                        ${visibilityBadge}
                    </div>
                `;
            }).join('');

            modal.classList.add('active');
        }

        function closeModal() {
            document.getElementById('eventModal').classList.remove('active');
        }

        function showAddEventModal() {
            document.getElementById('addEventModal').classList.add('active');
        }

        function closeAddEventModal() {
            document.getElementById('addEventModal').classList.remove('active');
        }

        document.getElementById('eventModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeModal();
            }
        });

        document.getElementById('addEventModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeAddEventModal();
            }
        });

        // Add hover effect to child list items
        document.querySelectorAll('#childList a').forEach(link => {
            link.addEventListener('mouseenter', function() {
                this.style.background = '#f3f4f6';
                this.style.transform = 'translateX(8px)';
            });
            link.addEventListener('mouseleave', function() {
                this.style.background = '#f9fafb';
                this.style.transform = 'translateX(0)';
            });
        });

        // Search/filter functionality
        function filterChildren() {
            const searchInput = document.getElementById('childSearch');
            const searchTerm = searchInput.value.toLowerCase();
            const childItems = document.querySelectorAll('.child-item');
            const childList = document.getElementById('childList');
            const noResults = document.getElementById('noResults');
            const resultCount = document.getElementById('resultCount');
            
            let visibleCount = 0;
            
            childItems.forEach(item => {
                const childName = item.getAttribute('data-child-name');
                
                if (childName.includes(searchTerm)) {
                    item.style.display = 'flex';
                    visibleCount++;
                } else {
                    item.style.display = 'none';
                }
            });
            
            // Show/hide no results message
            if (visibleCount === 0) {
                childList.style.display = 'none';
                noResults.style.display = 'block';
                resultCount.textContent = 'No children found';
            } else {
                childList.style.display = 'flex';
                noResults.style.display = 'none';
                resultCount.textContent = `Showing ${visibleCount} of <?php echo count($children_for_modal); ?> children`;
            }
        }

        // Reset search when modal opens
        function showAddEventModal() {
            document.getElementById('addEventModal').classList.add('active');
            document.getElementById('childSearch').value = '';
            filterChildren();
        }
    </script>
</body>
</html>