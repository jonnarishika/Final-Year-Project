<?php
/**
 * Email System Debug & Test Tool
 * Place this file in your DASHBOARDS root directory
 * Access via: http://yourdomain.com/test_email_triggers.php
 * 
 * This will:
 * 1. Check if children have active sponsors
 * 2. Test the sponsor query
 * 3. Send a test email
 * 4. Show detailed debugging info
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/db_config.php';
require_once __DIR__ . '/email_system/class.EmailSender.php';

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email System Debugger</title>
    <style>
        body {
            font-family: 'Consolas', 'Monaco', monospace;
            background: #1a1a2e;
            color: #eee;
            padding: 20px;
            line-height: 1.6;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
        }
        h1 {
            color: #00ff88;
            border-bottom: 3px solid #00ff88;
            padding-bottom: 10px;
        }
        h2 {
            color: #00bfff;
            margin-top: 30px;
            border-left: 4px solid #00bfff;
            padding-left: 10px;
        }
        .section {
            background: #16213e;
            padding: 20px;
            margin: 15px 0;
            border-radius: 8px;
            border: 1px solid #0f3460;
        }
        .success {
            color: #00ff88;
            font-weight: bold;
        }
        .error {
            color: #ff6b6b;
            font-weight: bold;
        }
        .warning {
            color: #ffd93d;
            font-weight: bold;
        }
        .info {
            color: #00bfff;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 10px 0;
        }
        th, td {
            padding: 12px;
            text-align: left;
            border: 1px solid #0f3460;
        }
        th {
            background: #0f3460;
            color: #00ff88;
        }
        tr:nth-child(even) {
            background: #1a1a2e;
        }
        .btn {
            display: inline-block;
            padding: 10px 20px;
            background: #00ff88;
            color: #1a1a2e;
            text-decoration: none;
            border-radius: 5px;
            font-weight: bold;
            margin: 5px;
            border: none;
            cursor: pointer;
        }
        .btn:hover {
            background: #00bfff;
        }
        pre {
            background: #000;
            padding: 15px;
            border-radius: 5px;
            overflow-x: auto;
            border: 1px solid #00ff88;
        }
        .query-box {
            background: #0a0a0a;
            padding: 15px;
            border-left: 4px solid #00bfff;
            margin: 10px 0;
            font-family: 'Courier New', monospace;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>📧 Email System Debugger & Tester</h1>
        
        <?php
        // ========================================
        // 1. CHECK DATABASE STRUCTURE
        // ========================================
        echo "<h2>1️⃣ Database Structure Check</h2>";
        echo "<div class='section'>";
        
        $tables_to_check = ['children', 'sponsors', 'sponsorships', 'users', 'notification_log'];
        $all_tables_exist = true;
        
        foreach ($tables_to_check as $table) {
            $result = $conn->query("SHOW TABLES LIKE '$table'");
            if ($result->num_rows > 0) {
                echo "<span class='success'>✅ Table '$table' exists</span><br>";
            } else {
                echo "<span class='error'>❌ Table '$table' is MISSING!</span><br>";
                $all_tables_exist = false;
            }
        }
        
        echo "</div>";
        
        // ========================================
        // 2. CHECK CHILDREN & SPONSORSHIPS
        // ========================================
        echo "<h2>2️⃣ Children & Sponsorship Data</h2>";
        echo "<div class='section'>";
        
        // Get all children
        $children_query = "SELECT 
            c.child_id,
            c.first_name,
            c.last_name,
            c.status,
            COUNT(CASE WHEN sp.end_date IS NULL THEN 1 END) as active_sponsors
        FROM children c
        LEFT JOIN sponsorships sp ON c.child_id = sp.child_id
        GROUP BY c.child_id
        ORDER BY c.child_id";
        
        $children_result = $conn->query($children_query);
        
        if ($children_result && $children_result->num_rows > 0) {
            echo "<p class='info'>Found " . $children_result->num_rows . " children in database:</p>";
            echo "<table>";
            echo "<tr><th>Child ID</th><th>Name</th><th>Status</th><th>Active Sponsors</th><th>Action</th></tr>";
            
            while ($child = $children_result->fetch_assoc()) {
                $color = $child['active_sponsors'] > 0 ? 'success' : 'warning';
                echo "<tr>";
                echo "<td>{$child['child_id']}</td>";
                echo "<td>{$child['first_name']} {$child['last_name']}</td>";
                echo "<td>{$child['status']}</td>";
                echo "<td class='$color'>{$child['active_sponsors']}</td>";
                echo "<td><a href='?test_child={$child['child_id']}' class='btn'>Test Emails</a></td>";
                echo "</tr>";
            }
            echo "</table>";
        } else {
            echo "<span class='error'>❌ No children found in database!</span>";
        }
        
        echo "</div>";
        
        // ========================================
        // 3. TEST SPECIFIC CHILD (if requested)
        // ========================================
        if (isset($_GET['test_child'])) {
            $test_child_id = intval($_GET['test_child']);
            
            echo "<h2>3️⃣ Testing Child ID: $test_child_id</h2>";
            
            // Get child details
            $child_query = "SELECT * FROM children WHERE child_id = ?";
            $stmt = $conn->prepare($child_query);
            $stmt->bind_param('i', $test_child_id);
            $stmt->execute();
            $child_result = $stmt->get_result();
            
            if ($child_result->num_rows === 0) {
                echo "<div class='section'>";
                echo "<span class='error'>❌ Child not found!</span>";
                echo "</div>";
            } else {
                $child = $child_result->fetch_assoc();
                
                echo "<div class='section'>";
                echo "<h3>Child Information:</h3>";
                echo "<p><strong>Name:</strong> {$child['first_name']} {$child['last_name']}</p>";
                echo "<p><strong>DOB:</strong> {$child['dob']}</p>";
                echo "<p><strong>Status:</strong> {$child['status']}</p>";
                echo "</div>";
                
                // Get sponsors for this child
                echo "<div class='section'>";
                echo "<h3>Finding Sponsors...</h3>";
                
                $sponsor_query = "SELECT 
                    s.sponsor_id,
                    s.user_id,
                    s.first_name,
                    s.last_name,
                    u.email,
                    u.user_role,
                    sp.start_date,
                    sp.end_date
                FROM sponsors s
                INNER JOIN users u ON s.user_id = u.user_id
                INNER JOIN sponsorships sp ON s.sponsor_id = sp.sponsor_id
                WHERE sp.child_id = ?
                ORDER BY sp.end_date IS NULL DESC, sp.start_date DESC";
                
                echo "<div class='query-box'>";
                echo "<strong>Query:</strong><br>";
                echo htmlspecialchars($sponsor_query);
                echo "</div>";
                
                $sponsor_stmt = $conn->prepare($sponsor_query);
                $sponsor_stmt->bind_param('i', $test_child_id);
                $sponsor_stmt->execute();
                $sponsor_result = $sponsor_stmt->get_result();
                
                if ($sponsor_result->num_rows === 0) {
                    echo "<span class='error'>❌ NO SPONSORS FOUND FOR THIS CHILD!</span><br><br>";
                    echo "<p class='warning'>This is why emails aren't being sent. The child needs active sponsors.</p>";
                    
                    // Show how to fix
                    echo "<h3>How to Fix:</h3>";
                    echo "<ol>";
                    echo "<li>Create a test sponsor user (or use existing)</li>";
                    echo "<li>Create a sponsorship record linking sponsor to child</li>";
                    echo "<li>Run this SQL:</li>";
                    echo "</ol>";
                    echo "<pre>";
                    echo "-- Example: Create sponsorship for child $test_child_id\n";
                    echo "INSERT INTO sponsorships (sponsor_id, child_id, start_date, end_date)\n";
                    echo "VALUES (\n";
                    echo "    (SELECT sponsor_id FROM sponsors WHERE user_id = YOUR_SPONSOR_USER_ID LIMIT 1),\n";
                    echo "    $test_child_id,\n";
                    echo "    CURDATE(),\n";
                    echo "    NULL  -- NULL means active sponsorship\n";
                    echo ");";
                    echo "</pre>";
                } else {
                    echo "<span class='success'>✅ Found {$sponsor_result->num_rows} sponsor(s)!</span><br><br>";
                    
                    echo "<table>";
                    echo "<tr><th>Sponsor ID</th><th>User ID</th><th>Name</th><th>Email</th><th>Role</th><th>Status</th></tr>";
                    
                    $active_sponsors = [];
                    while ($sponsor = $sponsor_result->fetch_assoc()) {
                        $is_active = is_null($sponsor['end_date']);
                        $status_class = $is_active ? 'success' : 'warning';
                        $status_text = $is_active ? 'ACTIVE' : 'ENDED';
                        
                        echo "<tr>";
                        echo "<td>{$sponsor['sponsor_id']}</td>";
                        echo "<td>{$sponsor['user_id']}</td>";
                        echo "<td>{$sponsor['first_name']} {$sponsor['last_name']}</td>";
                        echo "<td>{$sponsor['email']}</td>";
                        echo "<td>{$sponsor['user_role']}</td>";
                        echo "<td class='$status_class'>$status_text</td>";
                        echo "</tr>";
                        
                        if ($is_active && $sponsor['user_role'] === 'Sponsor') {
                            $active_sponsors[] = $sponsor;
                        }
                    }
                    echo "</table>";
                    
                    // Test sending emails
                    if (count($active_sponsors) > 0) {
                        echo "<br><h3>🧪 Test Sending Emails</h3>";
                        
                        if (isset($_GET['send_test'])) {
                            $emailSender = new EmailSender();
                            
                            foreach ($active_sponsors as $sponsor) {
                                echo "<div class='section'>";
                                echo "<p>Sending test achievement email to: <strong>{$sponsor['email']}</strong></p>";
                                
                                // Create fake achievement data
                                $achievement = [
                                    'upload_id' => 999,
                                    'description' => 'This is a TEST achievement email. If you receive this, the email system is working!',
                                    'upload_date' => date('Y-m-d')
                                ];
                                
                                try {
                                    $success = $emailSender->sendAchievementEmail($sponsor, $child, $achievement);
                                    
                                    if ($success) {
                                        echo "<span class='success'>✅ Email sent successfully!</span><br>";
                                        echo "<p class='info'>Check the inbox for: {$sponsor['email']}</p>";
                                    } else {
                                        echo "<span class='error'>❌ Email sending failed!</span><br>";
                                        echo "<p class='warning'>Check PHP error logs for details</p>";
                                    }
                                } catch (Exception $e) {
                                    echo "<span class='error'>❌ Error: " . htmlspecialchars($e->getMessage()) . "</span>";
                                }
                                
                                echo "</div>";
                            }
                            
                            // Check notification log
                            echo "<br><h3>📝 Notification Log Entries</h3>";
                            $log_query = "SELECT * FROM notification_log WHERE child_id = ? ORDER BY sent_at DESC LIMIT 5";
                            $log_stmt = $conn->prepare($log_query);
                            $log_stmt->bind_param('i', $test_child_id);
                            $log_stmt->execute();
                            $log_result = $log_stmt->get_result();
                            
                            if ($log_result->num_rows > 0) {
                                echo "<table>";
                                echo "<tr><th>Log ID</th><th>Type</th><th>Sent At</th><th>Status</th><th>Error</th></tr>";
                                while ($log = $log_result->fetch_assoc()) {
                                    $status_class = $log['delivery_status'] === 'sent' ? 'success' : 'error';
                                    echo "<tr>";
                                    echo "<td>{$log['log_id']}</td>";
                                    echo "<td>{$log['notification_type']}</td>";
                                    echo "<td>{$log['sent_at']}</td>";
                                    echo "<td class='$status_class'>{$log['delivery_status']}</td>";
                                    echo "<td>" . ($log['error_message'] ?? '-') . "</td>";
                                    echo "</tr>";
                                }
                                echo "</table>";
                            } else {
                                echo "<span class='warning'>⚠️ No log entries found</span>";
                            }
                            
                        } else {
                            echo "<a href='?test_child=$test_child_id&send_test=1' class='btn'>🚀 Send Test Achievement Email</a>";
                        }
                    } else {
                        echo "<br><span class='warning'>⚠️ No ACTIVE sponsors with 'Sponsor' role found. Cannot send test emails.</span>";
                    }
                }
                
                echo "</div>";
            }
        }
        
        // ========================================
        // 4. SYSTEM RECOMMENDATIONS
        // ========================================
        echo "<h2>4️⃣ System Status & Recommendations</h2>";
        echo "<div class='section'>";
        
        // Count total active sponsorships
        $active_count_query = "SELECT COUNT(*) as count FROM sponsorships WHERE end_date IS NULL";
        $active_count_result = $conn->query($active_count_query);
        $active_count = $active_count_result->fetch_assoc()['count'];
        
        if ($active_count === 0) {
            echo "<span class='error'>❌ CRITICAL: No active sponsorships in database!</span><br><br>";
            echo "<p><strong>This is the root cause of your email issue.</strong></p>";
            echo "<p>The email system requires:</p>";
            echo "<ol>";
            echo "<li>At least one child record</li>";
            echo "<li>At least one sponsor (user + sponsor record)</li>";
            echo "<li>An active sponsorship linking them (end_date = NULL)</li>";
            echo "</ol>";
        } else {
            echo "<span class='success'>✅ Found $active_count active sponsorship(s)</span><br>";
        }
        
        // Check notification log
        $log_count_query = "SELECT COUNT(*) as count FROM notification_log";
        $log_count_result = $conn->query($log_count_query);
        $log_count = $log_count_result->fetch_assoc()['count'];
        
        if ($log_count === 0) {
            echo "<span class='warning'>⚠️ Notification log is empty - emails have never been sent</span><br>";
        } else {
            echo "<span class='info'>ℹ️ Notification log has $log_count entries</span><br>";
        }
        
        echo "</div>";
        
        // ========================================
        // 5. QUICK FIX SQL
        // ========================================
        echo "<h2>5️⃣ Quick Fix: Create Test Sponsorship</h2>";
        echo "<div class='section'>";
        echo "<p>If you need to create a test sponsorship relationship, run this SQL:</p>";
        echo "<pre>";
        echo "-- Step 1: Find or create a test sponsor user\n";
        echo "-- (Make sure you have a user with role='Sponsor')\n\n";
        echo "-- Step 2: Create sponsorship\n";
        echo "INSERT INTO sponsorships (sponsor_id, child_id, start_date, end_date)\n";
        echo "SELECT \n";
        echo "    s.sponsor_id,\n";
        echo "    c.child_id,\n";
        echo "    CURDATE(),\n";
        echo "    NULL\n";
        echo "FROM sponsors s\n";
        echo "CROSS JOIN children c\n";
        echo "WHERE s.user_id = YOUR_SPONSOR_USER_ID  -- Replace with actual user_id\n";
        echo "  AND c.child_id = YOUR_CHILD_ID         -- Replace with actual child_id\n";
        echo "LIMIT 1;\n\n";
        echo "-- Step 3: Verify\n";
        echo "SELECT * FROM sponsorships WHERE end_date IS NULL;";
        echo "</pre>";
        echo "</div>";
        
        $conn->close();
        ?>
        
    </div>
</body>
</html>