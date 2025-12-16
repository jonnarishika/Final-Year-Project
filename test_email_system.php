<?php
/**
 * Email System Test Script
 * Tests all 5 email templates with sample data
 * 
 * Location: DASHBOARDS/test_email_system.php
 * 
 * USAGE:
 * 1. Update TEST_EMAIL constant with your email
 * 2. Run: php test_email_system.php
 * OR access via browser: http://yourdomain.com/DASHBOARDS/test_email_system.php
 */

// ADD THESE LINES AT THE TOP:
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

// Prevent direct browser access in production (comment out for testing)
// if (php_sapi_name() !== 'cli') {
//     die('This script can only be run from command line');
// }

require_once __DIR__ . '/email_system/class.EmailSender.php';
require_once __DIR__ . '/db_config.php';

// ========================================
// CONFIGURATION - UPDATE THIS!
// ========================================
define('TEST_EMAIL', 'polisettigeetika123@gmail.com'); // ⚠️ CHANGE THIS TO YOUR EMAIL
define('TEST_MODE', true); // Set to false to actually send emails

// ========================================
// SAMPLE DATA FOR TESTING
// ========================================

$sampleSponsor = [
    'user_id' => 1,
    'first_name' => 'John',
    'last_name' => 'Doe',
    'email' => TEST_EMAIL
];

$sampleChild = [
    'child_id' => 1,
    'first_name' => 'Rahul',
    'last_name' => 'Kumar',
    'dob' => '2015-03-15',
    'gender' => 'male'
];

// Use random IDs to avoid duplicate checks
$randomSuffix = time(); // Use timestamp to make IDs unique

$sampleAchievement = [
    'upload_id' => $randomSuffix + 1,
    'description' => 'Rahul won first place in the district-level drawing competition! His artwork on "My Dream Future" impressed all the judges and earned him a gold medal.',
    'upload_date' => date('Y-m-d')
];

$sampleReport = [
    'report_id' => $randomSuffix + 2,
    'report_text' => 'Rahul has shown excellent progress this quarter. His reading skills have improved significantly, and he is now reading at grade level. In mathematics, he has mastered multiplication tables and is working on division. His social skills have also developed well, and he actively participates in group activities.',
    'report_date' => date('Y-m-d')
];

$sampleEvent = [
    'event_id' => $randomSuffix + 3,
    'title' => 'Annual Day Celebration',
    'event_date' => date('Y-m-d', strtotime('+5 days')),
    'event_type' => 'celebration',
    'description' => 'Join us for our Annual Day celebration where children will showcase their talents through dance, music, and drama performances. Rahul will be performing in the cultural dance segment!'
];

// ========================================
// TEST FUNCTIONS
// ========================================

function printHeader($title) {
    echo "\n";
    echo "========================================\n";
    echo "  $title\n";
    echo "========================================\n";
}

function printSuccess($message) {
    echo "✅ SUCCESS: $message\n";
}

function printError($message) {
    echo "❌ ERROR: $message\n";
}

function printInfo($message) {
    echo "ℹ️  INFO: $message\n";
}

// ========================================
// MAIN TEST EXECUTION
// ========================================

printHeader("EMAIL SYSTEM TEST SCRIPT");

// Validate test email
if (TEST_EMAIL === 'your-email@example.com') {
    printError("Please update TEST_EMAIL constant with your actual email address!");
    exit(1);
}

printInfo("Test Email: " . TEST_EMAIL);
printInfo("Test Mode: " . (TEST_MODE ? 'ON (emails will be sent)' : 'OFF (dry run)'));
echo "\n";

// Initialize EmailSender
try {
    $emailSender = new EmailSender();
    printSuccess("EmailSender class initialized");
} catch (Exception $e) {
    printError("Failed to initialize EmailSender: " . $e->getMessage());
    exit(1);
}

// ========================================
// TEST 1: Birthday Email
// ========================================
printHeader("TEST 1: Birthday Email");
printInfo("Template: email_birthday.html");
printInfo("Simulating: Birthday in 2 days");

try {
    $birthdayDate = date('Y-m-d', strtotime('+2 days'));
    
    if (TEST_MODE) {
        $result = $emailSender->sendBirthdayEmail($sampleSponsor, $sampleChild, $birthdayDate);
        if ($result) {
            printSuccess("Birthday email sent successfully!");
        } else {
            printError("Birthday email failed to send");
        }
    } else {
        printInfo("Dry run - email not sent");
    }
} catch (Exception $e) {
    printError("Birthday email test failed: " . $e->getMessage());
}

sleep(2); // Pause between emails

// ========================================
// TEST 2: Achievement Email
// ========================================
printHeader("TEST 2: Achievement Email");
printInfo("Template: email_achievement.html");
printInfo("Simulating: New achievement uploaded");

try {
    if (TEST_MODE) {
        $result = $emailSender->sendAchievementEmail($sampleSponsor, $sampleChild, $sampleAchievement);
        if ($result) {
            printSuccess("Achievement email sent successfully!");
        } else {
            printError("Achievement email failed to send");
        }
    } else {
        printInfo("Dry run - email not sent");
    }
} catch (Exception $e) {
    printError("Achievement email test failed: " . $e->getMessage());
}

sleep(2); // Pause between emails

// ========================================
// TEST 3: Report Email
// ========================================
printHeader("TEST 3: Report Email");
printInfo("Template: email_report.html");
printInfo("Simulating: New progress report created");

try {
    if (TEST_MODE) {
        $result = $emailSender->sendReportEmail($sampleSponsor, $sampleChild, $sampleReport);
        if ($result) {
            printSuccess("Report email sent successfully!");
        } else {
            printError("Report email failed to send");
        }
    } else {
        printInfo("Dry run - email not sent");
    }
} catch (Exception $e) {
    printError("Report email test failed: " . $e->getMessage());
}

sleep(2); // Pause between emails

// ========================================
// TEST 4: Event Email
// ========================================
printHeader("TEST 4: Event Email (Instant)");
printInfo("Template: email_event.html");
printInfo("Simulating: New public event created");

try {
    if (TEST_MODE) {
        $result = $emailSender->sendEventEmail($sampleSponsor, $sampleChild, $sampleEvent);
        if ($result) {
            printSuccess("Event email sent successfully!");
        } else {
            printError("Event email failed to send");
        }
    } else {
        printInfo("Dry run - email not sent");
    }
} catch (Exception $e) {
    printError("Event email test failed: " . $e->getMessage());
}

sleep(2); // Pause between emails

// ========================================
// TEST 5: Event Reminder Email
// ========================================
printHeader("TEST 5: Event Reminder Email");
printInfo("Template: email_event_reminder.html");
printInfo("Simulating: Event reminder 2 days before");

try {
    // Modify event date to be 2 days from now for reminder
    $sampleEvent['event_date'] = date('Y-m-d', strtotime('+2 days'));
    
    if (TEST_MODE) {
        $result = $emailSender->sendEventReminderEmail($sampleSponsor, $sampleChild, $sampleEvent);
        if ($result) {
            printSuccess("Event reminder email sent successfully!");
        } else {
            printError("Event reminder email failed to send");
        }
    } else {
        printInfo("Dry run - email not sent");
    }
} catch (Exception $e) {
    printError("Event reminder email test failed: " . $e->getMessage());
}

// ========================================
// TEST SUMMARY
// ========================================
printHeader("TEST SUMMARY");

if (TEST_MODE) {
    echo "\n";
    printInfo("Total emails sent: 5");
    printInfo("Check your inbox at: " . TEST_EMAIL);
    printInfo("Don't forget to check SPAM folder!");
    echo "\n";
    printInfo("Check notification_log table for entries:");
    echo "   SELECT * FROM notification_log ORDER BY sent_at DESC LIMIT 5;\n";
} else {
    echo "\n";
    printInfo("This was a DRY RUN - no emails were sent");
    printInfo("Set TEST_MODE = true to actually send test emails");
}

echo "\n";
printHeader("TEMPLATE VERIFICATION");

// Check if all template files exist
$templates = [
    'email_birthday.html',
    'email_achievement.html',
    'email_report.html',
    'email_event.html',
    'email_event_reminder.html'
];

$templatePath = __DIR__ . '/email_system/templates/';
$allExist = true;

foreach ($templates as $template) {
    $fullPath = $templatePath . $template;
    if (file_exists($fullPath)) {
        printSuccess("Template found: $template");
    } else {
        printError("Template missing: $template");
        printInfo("Expected location: $fullPath");
        $allExist = false;
    }
}

if ($allExist) {
    echo "\n";
    printSuccess("All template files are in place!");
} else {
    echo "\n";
    printError("Some template files are missing. Please create them first.");
}

// ========================================
// DATABASE CHECK
// ========================================
printHeader("DATABASE VERIFICATION");

// Check if notification_log table exists
$query = "SHOW TABLES LIKE 'notification_log'";
$result = $conn->query($query);

if ($result && $result->num_rows > 0) {
    printSuccess("notification_log table exists");
    
    // Check table structure
    $query = "DESCRIBE notification_log";
    $result = $conn->query($query);
    
    if ($result) {
        printInfo("Table structure:");
        while ($row = $result->fetch_assoc()) {
            echo "   - " . $row['Field'] . " (" . $row['Type'] . ")\n";
        }
    }
} else {
    printError("notification_log table not found!");
    printInfo("Please create the table using the SQL from documentation");
}

echo "\n";

// Check if calendar_events table exists
$query = "SHOW TABLES LIKE 'calendar_events'";
$result = $conn->query($query);

if ($result && $result->num_rows > 0) {
    printSuccess("calendar_events table exists");
} else {
    printError("calendar_events table not found!");
    printInfo("Please create the table using the SQL from documentation");
}

// ========================================
// SMTP CONFIGURATION CHECK
// ========================================
printHeader("SMTP CONFIGURATION");

printInfo("SMTP Host: " . (defined('SMTP_HOST') ? SMTP_HOST : 'NOT DEFINED'));
printInfo("SMTP Port: " . (defined('SMTP_PORT') ? SMTP_PORT : 'NOT DEFINED'));
printInfo("SMTP Username: " . (defined('SMTP_USERNAME') ? SMTP_USERNAME : 'NOT DEFINED'));
printInfo("SMTP From Email: " . (defined('SMTP_FROM_EMAIL') ? SMTP_FROM_EMAIL : 'NOT DEFINED'));
printInfo("SMTP From Name: " . (defined('SMTP_FROM_NAME') ? SMTP_FROM_NAME : 'NOT DEFINED'));

if (!defined('SMTP_HOST') || !defined('SMTP_USERNAME') || !defined('SMTP_PASSWORD')) {
    echo "\n";
    printError("SMTP configuration incomplete!");
    printInfo("Please check signup_and_login/email_config.php");
}

// ========================================
// FINAL STATUS
// ========================================
echo "\n";
printHeader("TEST COMPLETE");

if (TEST_MODE) {
    echo "\n";
    echo "🎉 Test emails have been sent!\n";
    echo "\n";
    echo "Next Steps:\n";
    echo "1. Check your inbox at: " . TEST_EMAIL . "\n";
    echo "2. Check SPAM folder if emails don't appear\n";
    echo "3. Verify notification_log table has 5 new entries\n";
    echo "4. Review email designs and content\n";
    echo "5. Test on mobile devices\n";
    echo "\n";
} else {
    echo "\n";
    echo "📋 Dry run complete - no emails sent\n";
    echo "\n";
    echo "To send test emails:\n";
    echo "1. Update TEST_EMAIL constant\n";
    echo "2. Set TEST_MODE = true\n";
    echo "3. Run this script again\n";
    echo "\n";
}

echo "========================================\n\n";
?>