<?php
/**
 * AJAX endpoint for refreshing sponsor dashboard counts
 * ✅ FIXED VERSION - Uses children.sponsor_id (Method A)
 * 
 * File: get_dashboard_counts.php
 * Location: Save in the same folder as sponser_main_page.php
 */

session_start();
require_once __DIR__ . '/../db_config.php';

// Set JSON header
header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized', 'success' => false]);
    exit();
}

// Get sponsor_id from request
$sponsor_id = isset($_GET['sponsor_id']) ? intval($_GET['sponsor_id']) : 0;

if (!$sponsor_id) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid sponsor ID', 'success' => false]);
    exit();
}

// Verify that the logged-in user owns this sponsor account
$user_id = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT sponsor_id FROM sponsors WHERE sponsor_id = ? AND user_id = ?");
$stmt->bind_param("ii", $sponsor_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    http_response_code(403);
    echo json_encode(['error' => 'Forbidden - Not your account', 'success' => false]);
    exit();
}
$stmt->close();

try {
    // ✅ FIXED: Use children.sponsor_id (Method A - Direct Link)
    
    // 1. Sponsored children count
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM children WHERE sponsor_id = ?");
    $stmt->bind_param("i", $sponsor_id);
    $stmt->execute();
    $sponsored_children = $stmt->get_result()->fetch_assoc()['count'];
    $stmt->close();
    
    // 2. Total donated amount
    $stmt = $conn->prepare("SELECT COALESCE(SUM(amount), 0) as total FROM donations WHERE sponsor_id = ?");
    $stmt->bind_param("i", $sponsor_id);
    $stmt->execute();
    $total_donated = $stmt->get_result()->fetch_assoc()['total'];
    $stmt->close();
    
    // 3. New reports in last 30 days (via children.sponsor_id)
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
    
    // 4. Recent timeline events (if table exists)
    $table_check = $conn->query("SHOW TABLES LIKE 'timeline_events'");
    if ($table_check && $table_check->num_rows > 0) {
        $stmt = $conn->prepare("
            SELECT COUNT(*) as count 
            FROM timeline_events 
            WHERE sponsor_id = ? 
            AND created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
        ");
        $stmt->bind_param("i", $sponsor_id);
        $stmt->execute();
        $recent_events = $stmt->get_result()->fetch_assoc()['count'];
        $stmt->close();
    } else {
        $recent_events = 0;
    }
    
    // Build response
    $response = [
        'success' => true,
        'sponsored_children' => (int)$sponsored_children,
        'total_donated' => (float)$total_donated,
        'new_reports' => (int)$new_reports,
        'recent_events' => (int)$recent_events,
        'timestamp' => date('Y-m-d H:i:s')
    ];
    
    echo json_encode($response);
    
} catch (Exception $e) {
    error_log("Error in get_dashboard_counts.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'error' => 'Internal server error',
        'message' => $e->getMessage(),
        'success' => false
    ]);
}

$conn->close();
?>