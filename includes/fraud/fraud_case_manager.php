<?php
/**
 * FRAUD CASE MANAGER
 * Core fraud case CRUD operations
 * Author: Rishika
 */

require_once __DIR__ . '/../../db_config.php';

/**
 * Create a new fraud case
 */
function createFraudCase($sponsor_id, $donation_id, $severity, $trigger_type, $reason) {
    global $conn;
    
    // Check if active case already exists for this sponsor
    $existing = getActiveFraudCase($sponsor_id);
    if ($existing) {
        return $existing['fraud_case_id']; // Don't create duplicate
    }
    
    $stmt = $conn->prepare("
        INSERT INTO fraud_cases 
        (sponsor_id, related_donation_id, severity, trigger_type, reason, status) 
        VALUES (?, ?, ?, ?, ?, 'under_review')
    ");
    
    $stmt->bind_param("iisss", 
        $sponsor_id, 
        $donation_id, 
        $severity, 
        $trigger_type, 
        $reason
    );
    
    if ($stmt->execute()) {
        $case_id = $conn->insert_id;
        
        // Log creation
        error_log("Fraud case created: Case ID $case_id, Sponsor ID $sponsor_id, Trigger: $trigger_type");
        
        return $case_id;
    }
    
    return false;
}

/**
 * Get active fraud case for a sponsor
 * Returns the most recent non-cleared case
 */
function getActiveFraudCase($sponsor_id) {
    global $conn;
    
    $stmt = $conn->prepare("
        SELECT * FROM fraud_cases 
        WHERE sponsor_id = ? 
        AND status != 'cleared'
        ORDER BY created_at DESC 
        LIMIT 1
    ");
    
    $stmt->bind_param("i", $sponsor_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    return $result->fetch_assoc();
}

/**
 * Get complete fraud status for a sponsor
 */
function getSponsorFraudStatus($sponsor_id) {
    $case = getActiveFraudCase($sponsor_id);
    
    if (!$case) {
        return [
            'has_case' => false,
            'status' => null,
            'severity' => null,
            'case_id' => null,
            'reason' => null
        ];
    }
    
    return [
        'has_case' => true,
        'status' => $case['status'],
        'severity' => $case['severity'],
        'case_id' => $case['fraud_case_id'],
        'reason' => $case['reason'],
        'created_at' => $case['created_at']
    ];
}

/**
 * Check if sponsor is restricted (cannot donate)
 */
function isSponsorRestricted($sponsor_id) {
    $status = getSponsorFraudStatus($sponsor_id);
    
    if (!$status['has_case']) {
        return false;
    }
    
    // Restricted, frozen, or blocked = cannot donate
    return in_array($status['status'], ['restricted', 'frozen', 'blocked']);
}

/**
 * Update fraud case status
 */
function updateFraudCaseStatus($case_id, $new_status, $admin_id = null) {
    global $conn;
    
    $stmt = $conn->prepare("
        UPDATE fraud_cases 
        SET status = ?, updated_at = NOW() 
        WHERE fraud_case_id = ?
    ");
    
    $stmt->bind_param("si", $new_status, $case_id);
    $result = $stmt->execute();
    
    if ($result && $admin_id) {
        addFraudNote($case_id, $admin_id, "Status changed to: $new_status");
    }
    
    return $result;
}

/**
 * Add admin note to fraud case
 */
function addFraudNote($case_id, $admin_id, $note) {
    global $conn;
    
    $stmt = $conn->prepare("
        INSERT INTO fraud_case_notes 
        (fraud_case_id, admin_id, note) 
        VALUES (?, ?, ?)
    ");
    
    $stmt->bind_param("iis", $case_id, $admin_id, $note);
    return $stmt->execute();
}

/**
 * Get all fraud cases with filters
 */
function getAllFraudCases($status_filter = null, $severity_filter = null, $limit = 50) {
    global $conn;
    
    $query = "
        SELECT 
            fc.*,
            s.first_name, s.last_name, s.email,
            d.amount, d.donation_date
        FROM fraud_cases fc
        JOIN sponsors s ON fc.sponsor_id = s.sponsor_id
        LEFT JOIN donations d ON fc.related_donation_id = d.donation_id
        WHERE 1=1
    ";
    
    $params = [];
    $types = "";
    
    if ($status_filter) {
        $query .= " AND fc.status = ?";
        $params[] = $status_filter;
        $types .= "s";
    }
    
    if ($severity_filter) {
        $query .= " AND fc.severity = ?";
        $params[] = $severity_filter;
        $types .= "s";
    }
    
    $query .= " ORDER BY fc.created_at DESC LIMIT ?";
    $params[] = $limit;
    $types .= "i";
    
    $stmt = $conn->prepare($query);
    
    if ($types) {
        $stmt->bind_param($types, ...$params);
    }
    
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

/**
 * Get fraud case by ID with full details
 */
function getFraudCaseDetails($case_id) {
    global $conn;
    
    $stmt = $conn->prepare("
        SELECT 
            fc.*,
            s.sponsor_id, s.first_name, s.last_name, s.email, s.phone_no, s.dob,
            d.donation_id, d.amount, d.donation_date, d.receipt_no
        FROM fraud_cases fc
        JOIN sponsors s ON fc.sponsor_id = s.sponsor_id
        LEFT JOIN donations d ON fc.related_donation_id = d.donation_id
        WHERE fc.fraud_case_id = ?
    ");
    
    $stmt->bind_param("i", $case_id);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}

/**
 * Get all notes for a fraud case
 */
function getFraudCaseNotes($case_id) {
    global $conn;
    
    $stmt = $conn->prepare("
        SELECT 
            fcn.*,
            u.username as admin_name
        FROM fraud_case_notes fcn
        JOIN users u ON fcn.admin_id = u.user_id
        WHERE fcn.fraud_case_id = ?
        ORDER BY fcn.created_at DESC
    ");
    
    $stmt->bind_param("i", $case_id);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}
?>