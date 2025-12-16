<?php
/**
 * SIMPLE FRAUD TEST (Hardcoded IDs)
 * No session needed
 */

ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../db_config.php';
require_once __DIR__ . '/../includes/fraud/fraud_detector.php';

// HARDCODED - Your actual IDs
$sponsor_id = 3;  // From sponsors table
$user_id = 4;     // From users table

echo "<h1>🧪 Simple Fraud Detection Test</h1>";
echo "<style>
    body { font-family: monospace; padding: 2rem; background: #f5f5f5; }
    .box { background: white; padding: 1.5rem; border-radius: 8px; margin: 1rem 0; border: 2px solid #ddd; }
    .success { border-color: #4CAF50; background: #f1f8f4; }
    .error { border-color: #f44336; background: #ffebee; }
    .warning { border-color: #ff9800; background: #fff3e0; }
    pre { background: #f5f5f5; padding: 1rem; border-radius: 4px; overflow-x: auto; }
    h3 { margin-top: 0; }
</style>";

echo "<div class='box'>";
echo "<h3>🔍 Configuration</h3>";
echo "<p><strong>Sponsor ID:</strong> $sponsor_id</p>";
echo "<p><strong>User ID:</strong> $user_id</p>";
echo "</div>";

// Step 1: Check donations
echo "<div class='box'>";
echo "<h3>📊 Step 1: Check Donations</h3>";

$stmt = $conn->prepare("
    SELECT 
        COUNT(*) as total_count,
        SUM(CASE WHEN donation_date >= DATE_SUB(NOW(), INTERVAL 1 HOUR) THEN 1 ELSE 0 END) as last_hour,
        SUM(CASE WHEN status = 'Success' AND donation_date >= DATE_SUB(NOW(), INTERVAL 1 HOUR) THEN 1 ELSE 0 END) as success_last_hour
    FROM donations
    WHERE sponsor_id = ?
");
$stmt->bind_param("i", $sponsor_id);
$stmt->execute();
$stats = $stmt->get_result()->fetch_assoc();

echo "<p>Total donations: <strong>{$stats['total_count']}</strong></p>";
echo "<p>Last hour (all): <strong>{$stats['last_hour']}</strong></p>";
echo "<p>Last hour (success only): <strong>{$stats['success_last_hour']}</strong></p>";

if ($stats['success_last_hour'] >= 5) {
    echo "<p style='color: red; font-weight: bold;'>🚨 TRIGGER CONDITION MET: {$stats['success_last_hour']} >= 5</p>";
} else {
    echo "<p style='color: orange;'>⚠️ Not enough donations to trigger (need 5, have {$stats['success_last_hour']})</p>";
}
echo "</div>";

// Step 2: Check existing fraud cases
echo "<div class='box'>";
echo "<h3>🔎 Step 2: Check Existing Fraud Cases</h3>";

$stmt = $conn->prepare("
    SELECT * FROM fraud_cases 
    WHERE sponsor_id = ? 
    ORDER BY created_at DESC
");
$stmt->bind_param("i", $sponsor_id);
$stmt->execute();
$existing_cases = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

if (empty($existing_cases)) {
    echo "<p>✅ No existing fraud cases</p>";
} else {
    echo "<p style='color: orange;'>⚠️ Found " . count($existing_cases) . " existing case(s)</p>";
    foreach ($existing_cases as $case) {
        echo "<div style='background: #f5f5f5; padding: 0.5rem; margin: 0.5rem 0; border-radius: 4px;'>";
        echo "Case #{$case['fraud_case_id']} - Status: <strong>{$case['status']}</strong> - Severity: {$case['severity']}<br>";
        echo "Reason: {$case['reason']}<br>";
        echo "Created: {$case['created_at']}";
        echo "</div>";
    }
}
echo "</div>";

// Step 3: Run fraud detection
echo "<div class='box warning'>";
echo "<h3>🚀 Step 3: Running Fraud Detection</h3>";

// Get latest donation
$stmt = $conn->prepare("
    SELECT donation_id 
    FROM donations 
    WHERE sponsor_id = ? 
    ORDER BY donation_date DESC 
    LIMIT 1
");
$stmt->bind_param("i", $sponsor_id);
$stmt->execute();
$latest = $stmt->get_result()->fetch_assoc();

if (!$latest) {
    echo "<p style='color: red;'>❌ No donations found!</p>";
    echo "</div>";
    exit;
}

echo "<p>Using donation ID: <strong>{$latest['donation_id']}</strong></p>";

// Capture errors
$error_log_start = error_get_last();

ob_start();
try {
    runFraudDetection($sponsor_id, $latest['donation_id']);
    echo "<p style='color: green; font-weight: bold;'>✅ Fraud detection executed successfully!</p>";
} catch (Exception $e) {
    echo "<p style='color: red; font-weight: bold;'>❌ ERROR: " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
}
$output = ob_get_clean();
echo $output;

$error_log_end = error_get_last();
if ($error_log_end && $error_log_end !== $error_log_start) {
    echo "<p style='color: red;'>⚠️ PHP Error: " . htmlspecialchars($error_log_end['message']) . "</p>";
}

echo "</div>";

// Step 4: Verify result
echo "<div class='box'>";
echo "<h3>✅ Step 4: Verification</h3>";

$stmt = $conn->prepare("
    SELECT * FROM fraud_cases 
    WHERE sponsor_id = ? 
    ORDER BY created_at DESC 
    LIMIT 1
");
$stmt->bind_param("i", $sponsor_id);
$stmt->execute();
$new_case = $stmt->get_result()->fetch_assoc();

if ($new_case && (!$existing_cases || $new_case['fraud_case_id'] > $existing_cases[0]['fraud_case_id'])) {
    echo "<div class='success' style='padding: 1rem; border-radius: 4px;'>";
    echo "<h4 style='color: green; margin-top: 0;'>🎉 SUCCESS! NEW FRAUD CASE CREATED!</h4>";
    echo "<pre>";
    print_r($new_case);
    echo "</pre>";
    echo "</div>";
} else if ($new_case) {
    echo "<p style='color: orange;'>⚠️ Fraud case exists but wasn't newly created (already existed)</p>";
    echo "<pre>";
    print_r($new_case);
    echo "</pre>";
} else {
    echo "<div class='error' style='padding: 1rem; border-radius: 4px;'>";
    echo "<h4 style='color: red; margin-top: 0;'>❌ NO FRAUD CASE CREATED</h4>";
    echo "<p>Possible reasons:</p>";
    echo "<ul>";
    echo "<li>Not enough donations (need 5+ successful in last hour)</li>";
    echo "<li>Database insert failed (check foreign keys)</li>";
    echo "<li>Active case already exists</li>";
    echo "<li>PHP error (check above)</li>";
    echo "</ul>";
    echo "</div>";
}
echo "</div>";

// Step 5: Show recent donations
echo "<div class='box'>";
echo "<h3>📋 Step 5: Recent Donations (Last Hour)</h3>";

$stmt = $conn->prepare("
    SELECT donation_id, amount, donation_date, status
    FROM donations
    WHERE sponsor_id = ?
    AND donation_date >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
    ORDER BY donation_date DESC
");
$stmt->bind_param("i", $sponsor_id);
$stmt->execute();
$recent = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

echo "<p><strong>" . count($recent) . " donations found</strong></p>";
echo "<table border='1' cellpadding='8' cellspacing='0' style='width: 100%; border-collapse: collapse;'>";
echo "<tr style='background: #f5f5f5;'><th>ID</th><th>Amount</th><th>Date</th><th>Status</th></tr>";
foreach ($recent as $d) {
    $bg = $d['status'] === 'Success' ? '#e8f5e9' : '#fff3e0';
    echo "<tr style='background: $bg;'>";
    echo "<td>{$d['donation_id']}</td>";
    echo "<td>₹{$d['amount']}</td>";
    echo "<td>{$d['donation_date']}</td>";
    echo "<td><strong>{$d['status']}</strong></td>";
    echo "</tr>";
}
echo "</table>";
echo "</div>";

echo "<p style='margin-top: 2rem;'><a href='test_fraud_detection.php' style='padding: 10px 20px; background: #2196F3; color: white; text-decoration: none; border-radius: 5px;'>← Back to Dashboard</a></p>";
?>