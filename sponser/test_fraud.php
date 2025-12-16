<?php
session_start();
require_once __DIR__ . '/../db_config.php';
require_once __DIR__ . '/../includes/fraud/fraud_detector.php';

echo "<h1>🚨 Batch Fraud Detection</h1>";
echo "<p>Processing all sponsors with 5+ donations...</p><hr>";

// Find all sponsors with 5+ donations in last 24 hours
$query = "
    SELECT 
        d.sponsor_id,
        COUNT(*) as donation_count,
        MAX(d.donation_id) as latest_donation_id,
        s.first_name, s.last_name
    FROM donations d
    JOIN sponsors s ON d.sponsor_id = s.sponsor_id
    WHERE d.status = 'Success'
    AND d.donation_date >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
    GROUP BY d.sponsor_id
    HAVING COUNT(*) >= 5
    ORDER BY donation_count DESC
";

$result = $conn->query($query);

if ($result->num_rows == 0) {
    echo "<p>✅ No sponsors with suspicious activity found.</p>";
    exit;
}

echo "<h2>Found {$result->num_rows} sponsor(s) with 5+ donations</h2>";

while ($row = $result->fetch_assoc()) {
    echo "<div style='border: 2px solid #ff9800; padding: 15px; margin: 10px 0; border-radius: 8px;'>";
    echo "<h3>👤 {$row['first_name']} {$row['last_name']} (ID: {$row['sponsor_id']})</h3>";
    echo "<p>📊 Donations: <strong>{$row['donation_count']}</strong></p>";
    
    // Check if fraud case already exists
    $check = $conn->prepare("SELECT fraud_case_id FROM fraud_cases WHERE sponsor_id = ? AND status != 'cleared'");
    $check->bind_param("i", $row['sponsor_id']);
    $check->execute();
    $existing = $check->get_result()->fetch_assoc();
    
    if ($existing) {
        echo "<p>ℹ️ Already has fraud case (ID: {$existing['fraud_case_id']})</p>";
    } else {
        echo "<p>🔍 Running fraud detection...</p>";
        
        // Run detection
        runFraudDetection($row['sponsor_id'], $row['latest_donation_id']);
        
        // Check if case was created
        $verify = $conn->prepare("SELECT fraud_case_id, severity, reason FROM fraud_cases WHERE sponsor_id = ? ORDER BY created_at DESC LIMIT 1");
        $verify->bind_param("i", $row['sponsor_id']);
        $verify->execute();
        $new_case = $verify->get_result()->fetch_assoc();
        
        if ($new_case) {
            echo "<p style='color: green;'>✅ <strong>Fraud case created!</strong></p>";
            echo "<p>📋 Case ID: {$new_case['fraud_case_id']}<br>";
            echo "⚠️ Severity: {$new_case['severity']}<br>";
            echo "📝 Reason: {$new_case['reason']}</p>";
        } else {
            echo "<p style='color: red;'>❌ Failed to create fraud case</p>";
        }
    }
    
    echo "</div>";
}

echo "<hr><p><a href='test_fraud_detection.php'>← Back to Test Dashboard</a></p>";
?>