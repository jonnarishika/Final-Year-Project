<?php
/**
 * FRAUD ENFORCER
 * Enforcement actions: restrict, freeze, block, clear
 * Author: Rishika
 */

require_once __DIR__ . '/fraud_case_manager.php';

/**
 * Restrict sponsor (warning, can still use account but flagged)
 */
function restrictSponsor($case_id, $admin_id = null) {
    return updateFraudCaseStatus($case_id, 'restricted', $admin_id);
}

/**
 * Freeze sponsor (cannot make donations)
 */
function freezeSponsor($case_id, $admin_id) {
    global $conn;
    
    // Get case details
    $case = getFraudCaseDetails($case_id);
    if (!$case) {
        return false;
    }
    
    // Update fraud case status
    $result = updateFraudCaseStatus($case_id, 'frozen', $admin_id);
    
    if ($result) {
        addFraudNote($case_id, $admin_id, "Sponsor account frozen - donations blocked");
        
        // Log action
        error_log("Sponsor frozen: Sponsor ID {$case['sponsor_id']}, Case ID $case_id by Admin $admin_id");
    }
    
    return $result;
}

/**
 * Block sponsor (permanent ban, cannot login)
 */
function blockSponsor($case_id, $admin_id) {
    global $conn;
    
    // Get case details
    $case = getFraudCaseDetails($case_id);
    if (!$case) {
        return false;
    }
    
    // Update fraud case status
    $result = updateFraudCaseStatus($case_id, 'blocked', $admin_id);
    
    if ($result) {
        // Also update user account status
        $stmt = $conn->prepare("
            UPDATE users 
            SET is_active = 0 
            WHERE user_id = (
                SELECT user_id FROM sponsors WHERE sponsor_id = ?
            )
        ");
        $stmt->bind_param("i", $case['sponsor_id']);
        $stmt->execute();
        
        addFraudNote($case_id, $admin_id, "Sponsor account blocked - login disabled");
        
        // Log action
        error_log("Sponsor blocked: Sponsor ID {$case['sponsor_id']}, Case ID $case_id by Admin $admin_id");
    }
    
    return $result;
}

/**
 * Clear fraud case (sponsor is innocent)
 */
function clearFraudCase($case_id, $admin_id) {
    global $conn;
    
    // Get case details
    $case = getFraudCaseDetails($case_id);
    if (!$case) {
        return false;
    }
    
    // Update fraud case status
    $result = updateFraudCaseStatus($case_id, 'cleared', $admin_id);
    
    if ($result) {
        // Unblock user if they were blocked
        $stmt = $conn->prepare("
            UPDATE users 
            SET is_active = 1 
            WHERE user_id = (
                SELECT user_id FROM sponsors WHERE sponsor_id = ?
            )
        ");
        $stmt->bind_param("i", $case['sponsor_id']);
        $stmt->execute();
        
        addFraudNote($case_id, $admin_id, "Fraud case cleared - sponsor verified as legitimate");
        
        // Log action
        error_log("Fraud case cleared: Sponsor ID {$case['sponsor_id']}, Case ID $case_id by Admin $admin_id");
    }
    
    return $result;
}

/**
 * Check if sponsor can access donation pages
 */
function canSponsorDonate($sponsor_id) {
    $status = getSponsorFraudStatus($sponsor_id);
    
    if (!$status['has_case']) {
        return true;
    }
    
    // Only allow if cleared or under_review
    return in_array($status['status'], ['cleared', 'under_review']);
}

/**
 * Get restriction message for sponsor
 */
function getRestrictionMessage($sponsor_id) {
    $status = getSponsorFraudStatus($sponsor_id);
    
    if (!$status['has_case']) {
        return null;
    }
    
    $messages = [
        'under_review' => [
            'type' => 'warning',
            'title' => 'Account Under Review',
            'message' => 'Your account is currently under review due to unusual activity. You can continue using most features, but some actions may be limited.'
        ],
        'restricted' => [
            'type' => 'warning',
            'title' => 'Account Restricted',
            'message' => 'Your account has been restricted. Please contact support if you believe this is an error.'
        ],
        'frozen' => [
            'type' => 'error',
            'title' => 'Account Frozen',
            'message' => 'Your account has been temporarily frozen. Donations are currently disabled. Please contact support for assistance.'
        ],
        'blocked' => [
            'type' => 'error',
            'title' => 'Account Blocked',
            'message' => 'Your account has been blocked due to suspicious activity. Please contact support immediately.'
        ]
    ];
    
    return $messages[$status['status']] ?? null;
}

/**
 * Middleware: Block sponsor from donation pages
 * Call this at the top of checkout.php, payment pages, etc.
 */
function enforceDonationRestriction($sponsor_id, $redirect_url = 'sponsor_dashboard.php') {
    if (!canSponsorDonate($sponsor_id)) {
        $status = getSponsorFraudStatus($sponsor_id);
        
        session_start();
        $_SESSION['fraud_error'] = "You cannot make donations at this time. Reason: " . $status['reason'];
        
        header("Location: $redirect_url");
        exit();
    }
}

/**
 * Accept sponsor appeal
 */
function acceptAppeal($appeal_id, $admin_id) {
    global $conn;
    
    // Get appeal details
    $stmt = $conn->prepare("
        SELECT fraud_case_id FROM fraud_appeals WHERE appeal_id = ?
    ");
    $stmt->bind_param("i", $appeal_id);
    $stmt->execute();
    $appeal = $stmt->get_result()->fetch_assoc();
    
    if (!$appeal) {
        return false;
    }
    
    // Update appeal status
    $stmt = $conn->prepare("
        UPDATE fraud_appeals 
        SET status = 'accepted', updated_at = NOW() 
        WHERE appeal_id = ?
    ");
    $stmt->bind_param("i", $appeal_id);
    $stmt->execute();
    
    // Clear the fraud case
    clearFraudCase($appeal['fraud_case_id'], $admin_id);
    
    return true;
}

/**
 * Reject sponsor appeal
 */
function rejectAppeal($appeal_id, $admin_id, $reason) {
    global $conn;
    
    // Get appeal details
    $stmt = $conn->prepare("
        SELECT fraud_case_id FROM fraud_appeals WHERE appeal_id = ?
    ");
    $stmt->bind_param("i", $appeal_id);
    $stmt->execute();
    $appeal = $stmt->get_result()->fetch_assoc();
    
    if (!$appeal) {
        return false;
    }
    
    // Update appeal status
    $stmt = $conn->prepare("
        UPDATE fraud_appeals 
        SET status = 'rejected', updated_at = NOW() 
        WHERE appeal_id = ?
    ");
    $stmt->bind_param("i", $appeal_id);
    $stmt->execute();
    
    // Add note to fraud case
    addFraudNote($appeal['fraud_case_id'], $admin_id, "Appeal rejected: $reason");
    
    return true;
}
?>