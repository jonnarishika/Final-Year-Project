<?php
/**
 * Clear Test Email Logs
 * Removes test notification logs so you can test emails again
 * 
 * Location: DASHBOARDS/clear_test_logs.php
 * 
 * USAGE:
 * Run: php clear_test_logs.php
 */

require_once __DIR__ . '/db_config.php';

echo "========================================\n";
echo "  CLEAR TEST EMAIL LOGS\n";
echo "========================================\n\n";

// Check if notification_log table exists
$query = "SHOW TABLES LIKE 'notification_log'";
$result = $conn->query($query);

if (!$result || $result->num_rows === 0) {
    echo "❌ notification_log table doesn't exist!\n";
    echo "   Nothing to clear.\n\n";
    exit(0);
}

// Count existing logs
$query = "SELECT COUNT(*) as total FROM notification_log";
$result = $conn->query($query);
$row = $result->fetch_assoc();
$totalBefore = $row['total'];

echo "📊 Current notification logs: $totalBefore\n\n";

if ($totalBefore === 0) {
    echo "✅ No logs to clear!\n\n";
    exit(0);
}

// Show what will be deleted
echo "Logs by type:\n";
$query = "SELECT notification_type, COUNT(*) as count FROM notification_log GROUP BY notification_type";
$result = $conn->query($query);
while ($row = $result->fetch_assoc()) {
    echo "  - " . $row['notification_type'] . ": " . $row['count'] . "\n";
}

echo "\n";
echo "⚠️  WARNING: This will delete ALL notification logs!\n";
echo "   Type 'yes' to confirm: ";

// Get user confirmation
$handle = fopen("php://stdin", "r");
$line = trim(fgets($handle));
fclose($handle);

if (strtolower($line) !== 'yes') {
    echo "\n❌ Cancelled. No data was deleted.\n\n";
    exit(0);
}

// Delete all logs
$query = "DELETE FROM notification_log";
if ($conn->query($query)) {
    echo "\n✅ Successfully deleted $totalBefore notification log(s)!\n\n";
    echo "You can now run test_email_system.php again.\n\n";
} else {
    echo "\n❌ Error deleting logs: " . $conn->error . "\n\n";
}

echo "========================================\n\n";
?>