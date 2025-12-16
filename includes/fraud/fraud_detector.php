<?php
/**
 * FRAUD DETECTOR
 * Automatic fraud detection rules
 * Author: Rishika
 */

require_once __DIR__ . '/fraud_case_manager.php';

/**
 * Main fraud detection entry point
 * Called after every successful donation
 */
function runFraudDetection($sponsor_id, $donation_id) {
    global $conn;
    
    // DEBUG: Log the call
    error_log("=== FRAUD DETECTION STARTED ===");
    error_log("Sponsor ID: $sponsor_id");
    error_log("Donation ID: $donation_id");
    
    // Verify sponsor exists
    $stmt = $conn->prepare("SELECT sponsor_id FROM sponsors WHERE sponsor_id = ?");
    $stmt->bind_param("i", $sponsor_id);
    $stmt->execute();
    $sponsor_check = $stmt->get_result()->fetch_assoc();
    
    if (!$sponsor_check) {
        error_log("ERROR: Sponsor ID $sponsor_id not found in sponsors table!");
        return;
    }
    
    // Skip if sponsor already has an active case
    $existing_case = getActiveFraudCase($sponsor_id);
    if ($existing_case) {
        error_log("Skipping: Active fraud case already exists (Case ID: {$existing_case['fraud_case_id']})");
        return;
    }
    
    // Run all detection rules
    $triggers = [
        checkDonationSpamming($sponsor_id, $donation_id),
        checkPendingTransactions($sponsor_id),
        checkSmallDonations($sponsor_id, $donation_id),
        checkLargeDonation($sponsor_id, $donation_id)
    ];
    
    // DEBUG: Log triggers
    error_log("Triggers found: " . count(array_filter($triggers)));
    foreach ($triggers as $i => $trigger) {
        if ($trigger) {
            error_log("Trigger $i: {$trigger['severity']} - {$trigger['reason']}");
        }
    }
    
    // Filter out false triggers
    $triggers = array_filter($triggers, function($t) {
        return $t !== false;
    });
    
    // If any trigger fired, create fraud case
    if (!empty($triggers)) {
        // Use highest severity
        $severities = array_column($triggers, 'severity');
        $severity_map = ['low' => 1, 'medium' => 2, 'high' => 3, 'critical' => 4];
        $max_severity = array_reduce($severities, function($carry, $sev) use ($severity_map) {
            return ($severity_map[$sev] ?? 0) > ($severity_map[$carry] ?? 0) ? $sev : $carry;
        }, 'low');
        
        // Combine reasons
        $reasons = array_column($triggers, 'reason');
        $combined_reason = implode(" | ", $reasons);
        
        error_log("Creating fraud case with severity: $max_severity");
        
        $case_id = createFraudCase(
            $sponsor_id,
            $donation_id,
            $max_severity,
            'system',
            $combined_reason
        );
        
        if ($case_id) {
            error_log("✅ Fraud case created successfully! Case ID: $case_id");
        } else {
            error_log("❌ Failed to create fraud case!");
        }
    } else {
        error_log("No triggers fired - sponsor is clean");
    }
    
    error_log("=== FRAUD DETECTION ENDED ===");
}

/**
 * RULE 1: Donation Spamming
 * Multiple donations in short time
 */
function checkDonationSpamming($sponsor_id, $current_donation_id) {
    global $conn;
    
    $stmt = $conn->prepare("
        SELECT COUNT(*) as count 
        FROM donations 
        WHERE sponsor_id = ? 
        AND donation_date >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
        AND status = 'Success'
    ");
    
    $stmt->bind_param("i", $sponsor_id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    
    error_log("Spamming check: {$result['count']} donations in last hour");
    
    if ($result['count'] >= 5) {
        return [
            'severity' => 'high',
            'reason' => 'Donation spamming detected: ' . $result['count'] . ' donations in 1 hour'
        ];
    }
    
    return false;
}

/**
 * RULE 2: Too Many Pending Transactions
 */
function checkPendingTransactions($sponsor_id) {
    global $conn;
    
    $stmt = $conn->prepare("
        SELECT COUNT(*) as count 
        FROM donations 
        WHERE sponsor_id = ? 
        AND status = 'Pending'
        AND donation_date >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
    ");
    
    $stmt->bind_param("i", $sponsor_id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    
    error_log("Pending transactions check: {$result['count']} pending");
    
    if ($result['count'] >= 3) {
        return [
            'severity' => 'medium',
            'reason' => 'Multiple pending transactions: ' . $result['count'] . ' in 24 hours'
        ];
    }
    
    return false;
}

/**
 * RULE 3: Suspicious Small Donations
 * Many tiny donations to test stolen cards
 */
function checkSmallDonations($sponsor_id, $current_donation_id) {
    global $conn;
    
    $stmt = $conn->prepare("
        SELECT COUNT(*) as count 
        FROM donations 
        WHERE sponsor_id = ? 
        AND amount < 200
        AND status = 'Success'
        AND donation_date >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    ");
    
    $stmt->bind_param("i", $sponsor_id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    
    error_log("Small donations check: {$result['count']} donations under ₹200");
    
    if ($result['count'] >= 10) {
        return [
            'severity' => 'medium',
            'reason' => 'Suspicious pattern: 10+ donations under ₹200 in 7 days'
        ];
    }
    
    return false;
}

/**
 * RULE 4: Unusually Large First Donation
 */
function checkLargeDonation($sponsor_id, $current_donation_id) {
    global $conn;
    
    // Get total donation count
    $stmt = $conn->prepare("
        SELECT COUNT(*) as count, MAX(amount) as max_amount
        FROM donations 
        WHERE sponsor_id = ? 
        AND status = 'Success'
    ");
    
    $stmt->bind_param("i", $sponsor_id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    
    error_log("Large donation check: {$result['count']} total donations, max: ₹{$result['max_amount']}");
    
    // If first donation and over ₹50,000, flag
    if ($result['count'] == 1 && $result['max_amount'] >= 50000) {
        return [
            'severity' => 'high',
            'reason' => 'First donation unusually high: ₹' . number_format($result['max_amount'])
        ];
    }
    
    return false;
}

/**
 * Get donation history for a sponsor (for admin view)
 */
function getSponsorDonationHistory($sponsor_id, $days = 90) {
    global $conn;
    
    $stmt = $conn->prepare("
        SELECT 
            donation_id,
            amount,
            donation_date,
            payment_date,
            status,
            receipt_no,
            child_id
        FROM donations
        WHERE sponsor_id = ?
        AND donation_date >= DATE_SUB(NOW(), INTERVAL ? DAY)
        ORDER BY donation_date DESC
    ");
    
    $stmt->bind_param("ii", $sponsor_id, $days);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

/**
 * Get flagged donations for a sponsor
 */
function getFlaggedDonations($sponsor_id) {
    global $conn;
    
    $stmt = $conn->prepare("
        SELECT 
            d.donation_id,
            d.amount,
            d.donation_date,
            d.status,
            fc.fraud_case_id,
            fc.reason,
            fc.severity
        FROM donations d
        LEFT JOIN fraud_cases fc ON d.donation_id = fc.related_donation_id
        WHERE d.sponsor_id = ?
        AND fc.fraud_case_id IS NOT NULL
        ORDER BY d.donation_date DESC
    ");
    
    $stmt->bind_param("i", $sponsor_id);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}
?>