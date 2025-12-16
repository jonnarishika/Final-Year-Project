<?php
// staff_add_event.php
// Staff page to create calendar events for a child

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

require_once '../db_config.php';
require_once '../email_system/class.EmailSender.php';

// Get child ID from URL
$child_id = isset($_GET['child_id']) ? intval($_GET['child_id']) : 0;

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

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title']);
    $event_date = $_POST['event_date'];
    $event_type = $_POST['event_type'];
    $description = trim($_POST['description']);
    $is_public = isset($_POST['is_public']) ? 1 : 0;
    $created_by = $_SESSION['user_id'];
    
    if (empty($title) || empty($event_date) || empty($event_type)) {
        $error = "Title, date, and type are required.";
    } else {
        try {
            // Insert event into database
            $insert_query = "INSERT INTO calendar_events (child_id, title, event_date, event_type, description, is_public, created_by) 
                           VALUES (?, ?, ?, ?, ?, ?, ?)";
            $insert_stmt = $conn->prepare($insert_query);
            $insert_stmt->bind_param('issssii', $child_id, $title, $event_date, $event_type, $description, $is_public, $created_by);
            
            if ($insert_stmt->execute()) {
                $event_id = $insert_stmt->insert_id;
                
                // ===== EMAIL TRIGGER: Send event email if public =====
                if ($is_public == 1) {
                    try {
                        // Get all active sponsors for this child
                        $sponsor_query = "SELECT s.user_id, s.first_name, s.last_name, u.email 
                                        FROM sponsors s
                                        INNER JOIN users u ON s.user_id = u.user_id
                                        INNER JOIN sponsorships sp ON s.sponsor_id = sp.sponsor_id
                                        WHERE sp.child_id = ? 
                                        AND sp.end_date IS NULL
                                        AND u.user_role = 'Sponsor'";
                        
                        $sponsor_stmt = $conn->prepare($sponsor_query);
                        $sponsor_stmt->bind_param('i', $child_id);
                        $sponsor_stmt->execute();
                        $sponsor_result = $sponsor_stmt->get_result();
                        
                        // Initialize email sender
                        $emailSender = new EmailSender();
                        
                        // Prepare event data
                        $event = [
                            'event_id' => $event_id,
                            'title' => $title,
                            'event_date' => $event_date,
                            'event_type' => $event_type,
                            'description' => $description
                        ];
                        
                        // Send email to each sponsor
                        $emails_sent = 0;
                        while ($sponsor = $sponsor_result->fetch_assoc()) {
                            $success = $emailSender->sendEventEmail($sponsor, $child, $event);
                            if ($success) {
                                $emails_sent++;
                            }
                        }
                        
                        $sponsor_stmt->close();
                    } catch (Exception $e) {
                        error_log("Event email sending failed: " . $e->getMessage());
                    }
                }
                // ===== END EMAIL TRIGGER =====
                
                $insert_stmt->close();
                
                // Redirect back to child profile
                $message = $is_public ? "Event created successfully and sponsors notified!" : "Event created successfully (internal only)!";
                $_SESSION['success_message'] = $message;
                header("Location: child_edit.php?id=" . $child_id);
                exit();
            } else {
                $error = "Database error: " . $insert_stmt->error;
            }
            
            $insert_stmt->close();
        } catch (Exception $e) {
            $error = "Error: " . $e->getMessage();
        }
    }
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
    <title>Add Event - <?php echo htmlspecialchars($child['first_name'] . ' ' . $child['last_name']); ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- TinyMCE Rich Text Editor -->
    <script src="https://cdn.tiny.mce.com/1/tinymce/6/tinymce.min.js" referrerpolicy="origin"></script>
    
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
            max-width: 900px;
            margin: 0 auto;
            position: relative;
            z-index: 1;
        }

        .button-group {
            display: flex;
            gap: 12px;
            margin-bottom: 24px;
            flex-wrap: wrap;
        }

        .back-btn,
        .calendar-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 12px 20px;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 12px;
            color: white;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .back-btn {
            background: rgba(255, 255, 255, 0.15);
        }

        .calendar-btn {
            background: rgba(102, 126, 234, 0.3);
        }

        .back-btn:hover {
            background: rgba(255, 255, 255, 0.25);
            transform: translateX(-4px);
        }

        .calendar-btn:hover {
            background: rgba(102, 126, 234, 0.4);
            transform: translateY(-2px);
        }

        .glass-card {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border-radius: 24px;
            border: 1px solid rgba(255, 255, 255, 0.2);
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }

        .card-header {
            background: rgba(255, 255, 255, 0.05);
            padding: 32px 40px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .card-title {
            font-size: 2rem;
            font-weight: 700;
            color: white;
            margin-bottom: 8px;
        }

        .card-subtitle {
            font-size: 1.1rem;
            color: rgba(255, 255, 255, 0.8);
        }

        .card-body {
            padding: 40px;
        }

        .alert {
            padding: 16px 20px;
            border-radius: 12px;
            margin-bottom: 24px;
            font-weight: 500;
            backdrop-filter: blur(10px);
        }

        .alert-error {
            background: rgba(239, 68, 68, 0.2);
            border: 1px solid rgba(239, 68, 68, 0.3);
            color: #fee;
        }

        .form-group {
            margin-bottom: 28px;
        }

        .form-label {
            display: block;
            font-weight: 600;
            color: white;
            margin-bottom: 10px;
            font-size: 0.95rem;
        }

        .form-input,
        .form-select {
            width: 100%;
            padding: 14px 18px;
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 12px;
            font-size: 1rem;
            font-family: 'Inter', sans-serif;
            transition: all 0.2s ease;
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            color: white;
        }

        .form-input::placeholder {
            color: rgba(255, 255, 255, 0.5);
        }

        .form-input:focus,
        .form-select:focus {
            outline: none;
            background: rgba(255, 255, 255, 0.15);
            border-color: rgba(255, 255, 255, 0.4);
            box-shadow: 0 0 0 3px rgba(255, 255, 255, 0.1);
        }

        .form-select option {
            background: #2a5298;
            color: white;
        }

        .checkbox-wrapper {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 16px 20px;
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 12px;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .checkbox-wrapper:hover {
            background: rgba(255, 255, 255, 0.15);
        }

        .checkbox-wrapper input[type="checkbox"] {
            width: 20px;
            height: 20px;
            cursor: pointer;
        }

        .checkbox-label {
            color: white;
            font-weight: 500;
            cursor: pointer;
            user-select: none;
        }

        .checkbox-description {
            color: rgba(255, 255, 255, 0.7);
            font-size: 0.875rem;
            margin-top: 4px;
        }

        .btn-primary {
            width: 100%;
            padding: 16px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 12px;
            font-weight: 600;
            font-size: 1rem;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(102, 126, 234, 0.4);
        }

        .btn-primary:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }

        .tox-tinymce {
            border-radius: 12px !important;
            border: 1px solid rgba(255, 255, 255, 0.2) !important;
        }

        @media (max-width: 640px) {
            body {
                padding: 120px 15px 30px;
            }

            .card-header, .card-body {
                padding: 24px 20px;
            }

            .card-title {
                font-size: 1.5rem;
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
        <div class="button-group">
            <a href="child_edit.php?id=<?php echo $child_id; ?>" class="back-btn">
                <span>←</span>
                <span>Back to Profile</span>
            </a>
            <a href="staff_calendar.php" class="calendar-btn">
                <span>📅</span>
                <span>View Calendar</span>
            </a>
        </div>

        <div class="glass-card">
            <div class="card-header">
                <h1 class="card-title">Add Calendar Event</h1>
                <p class="card-subtitle">
                    For: <strong><?php echo htmlspecialchars($child['first_name'] . ' ' . $child['last_name']); ?></strong>
                </p>
            </div>

            <div class="card-body">
                <?php if (isset($error)): ?>
                    <div class="alert alert-error">
                        <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>

                <form method="POST" id="eventForm">
                    <div class="form-group">
                        <label class="form-label" for="title">Event Title *</label>
                        <input 
                            type="text" 
                            class="form-input" 
                            id="title" 
                            name="title" 
                            placeholder="e.g., Annual Day Celebration"
                            required
                        >
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="event_date">Event Date *</label>
                        <input 
                            type="date" 
                            class="form-input" 
                            id="event_date" 
                            name="event_date" 
                            min="<?php echo date('Y-m-d'); ?>"
                            required
                        >
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="event_type">Event Type *</label>
                        <select class="form-select" id="event_type" name="event_type" required>
                            <option value="">Select Type</option>
                            <option value="celebration">Celebration</option>
                            <option value="educational">Educational</option>
                            <option value="medical">Medical</option>
                            <option value="administrative">Administrative</option>
                            <option value="other">Other</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="description">Event Description</label>
                        <textarea 
                            id="description" 
                            name="description"
                            rows="10"
                        ></textarea>
                    </div>

                    <div class="form-group">
                        <label class="checkbox-wrapper">
                            <input type="checkbox" id="is_public" name="is_public" checked>
                            <div>
                                <div class="checkbox-label">Make this event visible to sponsors</div>
                                <div class="checkbox-description">
                                    If checked, sponsors will receive an email notification and see this event on their calendar
                                </div>
                            </div>
                        </label>
                    </div>

                    <button type="submit" class="btn-primary" id="submitBtn">
                        Create Event
                    </button>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Wait for TinyMCE to load before initializing
        function initializeTinyMCE() {
            if (typeof tinymce !== 'undefined') {
                tinymce.init({
                    selector: '#description',
                    height: 300,
                    menubar: false,
                    plugins: [
                        'advlist', 'autolink', 'lists', 'link', 'charmap', 'preview',
                        'searchreplace', 'visualblocks', 'code', 'fullscreen',
                        'insertdatetime', 'table', 'wordcount'
                    ],
                    toolbar: 'undo redo | formatselect | bold italic underline | alignleft aligncenter alignright alignjustify | bullist numlist | removeformat',
                    content_style: 'body { font-family: Inter, sans-serif; font-size: 14px; }',
                    placeholder: 'Describe the event. Include what will happen, who can attend, and any special instructions...'
                });
            } else {
                // Retry after a short delay if TinyMCE hasn't loaded yet
                setTimeout(initializeTinyMCE, 100);
            }
        }

        // Initialize TinyMCE when page loads
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', initializeTinyMCE);
        } else {
            initializeTinyMCE();
        }

        // Update button text based on checkbox
        const checkbox = document.getElementById('is_public');
        const submitBtn = document.getElementById('submitBtn');
        
        checkbox.addEventListener('change', function() {
            if (this.checked) {
                submitBtn.textContent = 'Create Event & Notify Sponsors';
            } else {
                submitBtn.textContent = 'Create Event (Internal Only)';
            }
        });

        // Form submission handling
        document.getElementById('eventForm').addEventListener('submit', function(e) {
            submitBtn.disabled = true;
            submitBtn.textContent = 'Creating Event...';
        });
    </script>
</body>
</html>