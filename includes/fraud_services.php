<?php


require_once __DIR__ . '/../db_config.php';
require_once 'risk_engine.php';

/**
 * ============================================
 * STAFF FUNCTIONS (REPORTING)
 * ============================================
 */

/**
 * Staff creates a fraud signal/report
 * 
 * @param int $sponsor_id Target sponsor
 * @param int $staff_user_id Staff member creating report
 * @param string $description Reason for report
 * @param int|null $donation_id Optional specific donation
 * @return array Success status and signal_id
 */
function createStaffReport($conn, $sponsor_id, $staff_user_id, $description, $donation_id = null) {
    // Validate staff role
    $stmt = $conn->prepare("SELECT user_role FROM users WHERE user_id = ?");
    $stmt->bind_param('i', $staff_user_id);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    
    if ($user['user_role'] !== 'Staff' && $user['user_role'] !== 'Admin') {
        return ['success' => false, 'error' => 'Unauthorized: Only staff can create reports'];
    }
    
    // Insert staff signal
    $weight = FRAUD_WEIGHTS['STAFF_REPORT'];
    $stmt = $conn->prepare("
        INSERT INTO fraud_signals 
        (sponsor_id, donation_id, signal_type, signal_source, weight, description, created_by)
        VALUES (?, ?, 'staff_report', 'staff', ?, ?, ?)
    ");
    $stmt->bind_param('iiisi', $sponsor_id, $donation_id, $weight, $description, $staff_user_id);
    
    if ($stmt->execute()) {
        $signal_id = $conn->insert_id;
        
        // Update risk score
        addRiskPoints($conn, $sponsor_id, $weight);
        
        // Check if case should be created
        $case_id = checkAndCreateCase($conn, $sponsor_id, $staff_user_id);
        
        return [
            'success' => true,
            'signal_id' => $signal_id,
            'case_created' => $case_id !== null,
            'case_id' => $case_id,
            'new_risk' => getSponsorRiskScore($conn, $sponsor_id)
        ];
    }
    
    return ['success' => false, 'error' => 'Failed to create report'];
}

/**
 * Get all reports created by a specific staff member
 * 
 * @param int $staff_user_id Staff member ID
 * @return array List of reports
 */
function getStaffReports($conn, $staff_user_id) {
    $stmt = $conn->prepare("
        SELECT 
            fs.signal_id,
            fs.sponsor_id,
            fs.donation_id,
            fs.description,
            fs.weight,
            fs.created_at,
            CONCAT(s.first_name, ' ', s.last_name) as sponsor_name,
            u.email as sponsor_email,
            srs.risk_score,
            srs.risk_level,
            CASE 
                WHEN fc.fraud_case_id IS NOT NULL THEN fc.status
                ELSE NULL
            END as case_status
        FROM fraud_signals fs
        INNER JOIN sponsors s ON fs.sponsor_id = s.sponsor_id
        INNER JOIN users u ON s.user_id = u.user_id
        LEFT JOIN sponsor_risk_scores srs ON fs.sponsor_id = srs.sponsor_id
        LEFT JOIN fraud_cases fc ON fs.sponsor_id = fc.sponsor_id 
            AND fc.status NOT IN ('cleared', 'blocked')
        WHERE fs.created_by = ?
        AND fs.signal_source = 'staff'
        ORDER BY fs.created_at DESC
    ");
    $stmt->bind_param('i', $staff_user_id);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

/**
 * ============================================
 * ADMIN FUNCTIONS (REVIEW & DECISIONS)
 * ============================================
 */

/**
 * Get all fraud signals (for signals_overview.php)
 * 
 * @param array $filters Optional filters (type, source, sponsor_id)
 * @return array List of signals
 */
function getAllSignals($conn, $filters = []) {
    $query = "
        SELECT 
            fs.signal_id,
            fs.sponsor_id,
            fs.signal_type,
            fs.signal_source,
            fs.weight,
            fs.description,
            fs.created_at,
            fs.donation_id,
            CONCAT(s.first_name, ' ', s.last_name) as sponsor_name,
            u.email as sponsor_email,
            CONCAT(creator.first_name, ' ', creator.last_name) as created_by_name,
            srs.risk_score,
            srs.risk_level,
            CASE 
                WHEN fc.fraud_case_id IS NOT NULL THEN fc.fraud_case_id
                ELSE NULL
            END as active_case_id
        FROM fraud_signals fs
        INNER JOIN sponsors s ON fs.sponsor_id = s.sponsor_id
        INNER JOIN users u ON s.user_id = u.user_id
        LEFT JOIN users creator ON fs.created_by = creator.user_id
        LEFT JOIN sponsor_risk_scores srs ON fs.sponsor_id = srs.sponsor_id
        LEFT JOIN fraud_cases fc ON fs.sponsor_id = fc.sponsor_id 
            AND fc.status NOT IN ('cleared', 'blocked')
        WHERE 1=1
    ";
    
    $params = [];
    $types = '';
    
    if (!empty($filters['type'])) {
        $query .= " AND fs.signal_type = ?";
        $params[] = $filters['type'];
        $types .= 's';
    }
    
    if (!empty($filters['source'])) {
        $query .= " AND fs.signal_source = ?";
        $params[] = $filters['source'];
        $types .= 's';
    }
    
    if (!empty($filters['sponsor_id'])) {
        $query .= " AND fs.sponsor_id = ?";
        $params[] = $filters['sponsor_id'];
        $types .= 'i';
    }
    
    $query .= " ORDER BY fs.created_at DESC";
    
    $stmt = $conn->prepare($query);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

/**
 * Get complete fraud case details (for review_case.php)
 * 
 * @param int $sponsor_id Sponsor to review
 * @return array|null Case details with signals, donations, history
 */
function getFraudCaseDetails($conn, $sponsor_id) {
    // Get sponsor info
    $stmt = $conn->prepare("
        SELECT 
            s.sponsor_id,
            s.first_name,
            s.dob,
            s.address,
            s.profile_picture,
            s.is_flagged,
            s.flag_reason,
            u.user_id,
            u.email,
            u.phone_no,
            u.created_at as account_created
        FROM sponsors s
        INNER JOIN users u ON s.user_id = u.user_id
        WHERE s.sponsor_id = ?
    ");
    $stmt->bind_param('i', $sponsor_id);
    $stmt->execute();
    $sponsor = $stmt->get_result()->fetch_assoc();
    
    if (!$sponsor) {
        return null;
    }
    
    // Get risk score
    $risk = getSponsorRiskScore($conn, $sponsor_id);
    
    // Get all signals
    $stmt = $conn->prepare("
        SELECT 
            fs.*,
            CONCAT(u.first_name, ' ', u.last_name) as created_by_name
        FROM fraud_signals fs
        LEFT JOIN users u ON fs.created_by = u.user_id
        WHERE fs.sponsor_id = ?
        ORDER BY fs.created_at DESC
    ");
    $stmt->bind_param('i', $sponsor_id);
    $stmt->execute();
    $signals = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    // Get active case if exists
    $stmt = $conn->prepare("
        SELECT 
            fc.*,
            CONCAT(u.first_name, ' ', u.last_name) as opened_by_name
        FROM fraud_cases fc
        INNER JOIN users u ON fc.opened_by = u.user_id
        WHERE fc.sponsor_id = ?
        AND fc.status NOT IN ('cleared', 'blocked')
        ORDER BY fc.created_at DESC
        LIMIT 1
    ");
    $stmt->bind_param('i', $sponsor_id);
    $stmt->execute();
    $active_case = $stmt->get_result()->fetch_assoc();
    
    // Get case notes if case exists
    $case_notes = [];
    if ($active_case) {
        $stmt = $conn->prepare("
            SELECT 
                fcn.*,
                CONCAT(u.first_name, ' ', u.last_name) as admin_name
            FROM fraud_case_notes fcn
            INNER JOIN users u ON fcn.admin_id = u.user_id
            WHERE fcn.fraud_case_id = ?
            ORDER BY fcn.created_at DESC
        ");
        $stmt->bind_param('i', $active_case['fraud_case_id']);
        $stmt->execute();
        $case_notes = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
    
    // Get donation history (last 90 days)
    $stmt = $conn->prepare("
        SELECT 
            d.*,
            CONCAT(c.first_name, ' ', c.last_name) as child_name
        FROM donations d
        INNER JOIN children c ON d.child_id = c.child_id
        WHERE d.sponsor_id = ?
        AND d.donation_date >= DATE_SUB(NOW(), INTERVAL 90 DAY)
        ORDER BY d.donation_date DESC
    ");
    $stmt->bind_param('i', $sponsor_id);
    $stmt->execute();
    $donations = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    // Get pending appeals
    $appeals = [];
    if ($active_case) {
        $stmt = $conn->prepare("
            SELECT * FROM fraud_appeals
            WHERE fraud_case_id = ?
            ORDER BY created_at DESC
        ");
        $stmt->bind_param('i', $active_case['fraud_case_id']);
        $stmt->execute();
        $appeals = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
    
    return [
        'sponsor' => $sponsor,
        'risk' => $risk,
        'signals' => $signals,
        'active_case' => $active_case,
        'case_notes' => $case_notes,
        'donations' => $donations,
        'appeals' => $appeals,
        'signal_breakdown' => getSponsorFraudSummary($conn, $sponsor_id)
    ];
}

/**
 * Admin takes action on a case
 * 
 * @param int $sponsor_id Target sponsor
 * @param int $admin_id Admin making decision
 * @param string $action Action to take (clear, restrict, freeze, block)
 * @param string $justification Required admin note
 * @return array Success status
 */
function adminTakeAction($conn, $sponsor_id, $admin_id, $action, $justification) {
    // Validate action
    $valid_actions = ['clear', 'restrict', 'freeze', 'block'];
    if (!in_array($action, $valid_actions)) {
        return ['success' => false, 'error' => 'Invalid action'];
    }
    
    // Justification is MANDATORY
    if (empty(trim($justification))) {
        return ['success' => false, 'error' => 'Justification is required for all decisions'];
    }
    
    $conn->begin_transaction();
    
    try {
        // Get or create case
        $stmt = $conn->prepare("
            SELECT fraud_case_id FROM fraud_cases
            WHERE sponsor_id = ?
            AND status NOT IN ('cleared', 'blocked')
            ORDER BY created_at DESC
            LIMIT 1
        ");
        $stmt->bind_param('i', $sponsor_id);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        
        $case_id = $result['fraud_case_id'] ?? null;
        
        // Create case if doesn't exist
        if (!$case_id) {
            $risk = getSponsorRiskScore($conn, $sponsor_id);
            $summary = "Case created during admin review";
            
            $stmt = $conn->prepare("
                INSERT INTO fraud_cases 
                (sponsor_id, opened_by, current_risk_score, summary, status)
                VALUES (?, ?, ?, ?, 'under_review')
            ");
            $stmt->bind_param('iiis', $sponsor_id, $admin_id, $risk['risk_score'], $summary);
            $stmt->execute();
            $case_id = $conn->insert_id;
        }
        
        // Map action to case status
        $status_map = [
            'clear' => 'cleared',
            'restrict' => 'restricted',
            'freeze' => 'frozen',
            'block' => 'blocked'
        ];
        $new_status = $status_map[$action];
        
        // Update case status
        $stmt = $conn->prepare("
            UPDATE fraud_cases 
            SET status = ?, current_risk_score = ?
            WHERE fraud_case_id = ?
        ");
        $risk = getSponsorRiskScore($conn, $sponsor_id);
        $stmt->bind_param('sii', $new_status, $risk['risk_score'], $case_id);
        $stmt->execute();
        
        // Add admin note (MANDATORY AUDIT TRAIL)
        $stmt = $conn->prepare("
            INSERT INTO fraud_case_notes (fraud_case_id, admin_id, note)
            VALUES (?, ?, ?)
        ");
        $note = "Action: {$action} | Justification: {$justification}";
        $stmt->bind_param('iis', $case_id, $admin_id, $note);
        $stmt->execute();
        
        // Update sponsor flag status
        if ($action === 'clear') {
            $stmt = $conn->prepare("
                UPDATE sponsors 
                SET is_flagged = 0, flag_reason = NULL
                WHERE sponsor_id = ?
            ");
            $stmt->bind_param('i', $sponsor_id);
            $stmt->execute();
            
            // Optionally reduce risk score on clear
            updateRiskScore($conn, $sponsor_id, max(0, $risk['risk_score'] - 20));
        } else {
            $flag_reasons = [
                'restrict' => 'Account restricted due to suspicious activity',
                'freeze' => 'Account frozen pending investigation',
                'block' => 'Account blocked due to confirmed fraud'
            ];
            
            $stmt = $conn->prepare("
                UPDATE sponsors 
                SET is_flagged = 1, flag_reason = ?
                WHERE sponsor_id = ?
            ");
            $flag_reason = $flag_reasons[$action];
            $stmt->bind_param('si', $flag_reason, $sponsor_id);
            $stmt->execute();
        }
        
        $conn->commit();
        
        return [
            'success' => true,
            'case_id' => $case_id,
            'new_status' => $new_status,
            'action' => $action
        ];
        
    } catch (Exception $e) {
        $conn->rollback();
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Add admin note to case (for ongoing documentation)
 * 
 * @param int $case_id Fraud case ID
 * @param int $admin_id Admin adding note
 * @param string $note Note content
 * @return bool Success
 */
function addCaseNote($conn, $case_id, $admin_id, $note) {
    $stmt = $conn->prepare("
        INSERT INTO fraud_case_notes (fraud_case_id, admin_id, note)
        VALUES (?, ?, ?)
    ");
    $stmt->bind_param('iis', $case_id, $admin_id, $note);
    return $stmt->execute();
}

/**
 * ============================================
 * SPONSOR FUNCTIONS (APPEALS)
 * ============================================
 */

/**
 * Get sponsor's flagged status and case info
 * 
 * @param int $sponsor_id Sponsor ID
 * @return array|null Flag status and active case
 */
function getSponsorFlagStatus($conn, $sponsor_id) {
    $stmt = $conn->prepare("
        SELECT 
            s.is_flagged,
            s.flag_reason,
            srs.risk_score,
            srs.risk_level,
            fc.fraud_case_id,
            fc.status as case_status,
            fc.created_at as case_opened
        FROM sponsors s
        LEFT JOIN sponsor_risk_scores srs ON s.sponsor_id = srs.sponsor_id
        LEFT JOIN fraud_cases fc ON s.sponsor_id = fc.sponsor_id
            AND fc.status NOT IN ('cleared', 'blocked')
        WHERE s.sponsor_id = ?
    ");
    $stmt->bind_param('i', $sponsor_id);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}

/**
 * Sponsor submits an appeal
 * 
 * @param int $sponsor_id Sponsor submitting appeal
 * @param int $case_id Fraud case to appeal
 * @param string $appeal_text Sponsor's explanation
 * @param string|null $attachment Optional file path
 * @return array Success status
 */
function submitAppeal($conn, $sponsor_id, $case_id, $appeal_text, $attachment = null) {
    // Validate case belongs to sponsor
    $stmt = $conn->prepare("
        SELECT sponsor_id FROM fraud_cases WHERE fraud_case_id = ?
    ");
    $stmt->bind_param('i', $case_id);
    $stmt->execute();
    $case = $stmt->get_result()->fetch_assoc();
    
    if (!$case || $case['sponsor_id'] != $sponsor_id) {
        return ['success' => false, 'error' => 'Invalid case or unauthorized'];
    }
    
    // Check if appeal already exists
    $stmt = $conn->prepare("
        SELECT appeal_id FROM fraud_appeals
        WHERE fraud_case_id = ? AND status = 'pending'
    ");
    $stmt->bind_param('i', $case_id);
    $stmt->execute();
    
    if ($stmt->get_result()->num_rows > 0) {
        return ['success' => false, 'error' => 'An appeal is already pending for this case'];
    }
    
    // Create appeal
    $stmt = $conn->prepare("
        INSERT INTO fraud_appeals 
        (fraud_case_id, sponsor_id, appeal_text, attachment, status)
        VALUES (?, ?, ?, ?, 'pending')
    ");
    $stmt->bind_param('iiss', $case_id, $sponsor_id, $appeal_text, $attachment);
    
    if ($stmt->execute()) {
        return [
            'success' => true,
            'appeal_id' => $conn->insert_id
        ];
    }
    
    return ['success' => false, 'error' => 'Failed to submit appeal'];
}

/**
 * Get all pending appeals (for admin appeals.php)
 * 
 * @return array List of pending appeals
 */
function getPendingAppeals($conn) {
    $stmt = $conn->prepare("
        SELECT 
            fa.appeal_id,
            fa.fraud_case_id,
            fa.sponsor_id,
            fa.appeal_text,
            fa.attachment,
            fa.created_at,
            CONCAT(s.first_name, ' ', s.last_name) as sponsor_name,
            u.email as sponsor_email,
            fc.status as case_status,
            fc.current_risk_score,
            srs.risk_level
        FROM fraud_appeals fa
        INNER JOIN sponsors s ON fa.sponsor_id = s.sponsor_id
        INNER JOIN users u ON s.user_id = u.user_id
        INNER JOIN fraud_cases fc ON fa.fraud_case_id = fc.fraud_case_id
        LEFT JOIN sponsor_risk_scores srs ON fa.sponsor_id = srs.sponsor_id
        WHERE fa.status = 'pending'
        ORDER BY fa.created_at ASC
    ");
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

/**
 * Admin reviews an appeal
 * 
 * @param int $appeal_id Appeal to review
 * @param int $admin_id Admin reviewing
 * @param string $decision 'accepted' or 'rejected'
 * @param string $justification Admin's reasoning
 * @return array Success status
 */
function reviewAppeal($conn, $appeal_id, $admin_id, $decision, $justification) {
    if (!in_array($decision, ['accepted', 'rejected'])) {
        return ['success' => false, 'error' => 'Invalid decision'];
    }
    
    if (empty(trim($justification))) {
        return ['success' => false, 'error' => 'Justification required'];
    }
    
    $conn->begin_transaction();
    
    try {
        // Update appeal
        $stmt = $conn->prepare("
            UPDATE fraud_appeals 
            SET status = ?, reviewed_by = ?
            WHERE appeal_id = ?
        ");
        $stmt->bind_param('sii', $decision, $admin_id, $appeal_id);
        $stmt->execute();
        
        // Get appeal details
        $stmt = $conn->prepare("
            SELECT fraud_case_id, sponsor_id FROM fraud_appeals WHERE appeal_id = ?
        ");
        $stmt->bind_param('i', $appeal_id);
        $stmt->execute();
        $appeal = $stmt->get_result()->fetch_assoc();
        
        // Add note to case
        $note = "Appeal {$decision} | Justification: {$justification}";
        addCaseNote($conn, $appeal['fraud_case_id'], $admin_id, $note);
        
        // If accepted, reduce risk score and potentially clear case
        if ($decision === 'accepted') {
            $risk = getSponsorRiskScore($conn, $appeal['sponsor_id']);
            $new_score = max(0, $risk['risk_score'] - 30); // Reduce by 30 points
            updateRiskScore($conn, $appeal['sponsor_id'], $new_score);
            
            // If score now low, auto-clear case
            if ($new_score < 20) {
                adminTakeAction($conn, $appeal['sponsor_id'], $admin_id, 'clear', 'Appeal accepted and risk score reduced to safe level');
            }
        }
        
        $conn->commit();
        return ['success' => true, 'decision' => $decision];
        
    } catch (Exception $e) {
        $conn->rollback();
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * ============================================
 * UTILITY FUNCTIONS
 * ============================================
 */

/**
 * Check if sponsor can donate (enforcement point)
 * 
 * @param int $sponsor_id Sponsor attempting donation
 * @return array Can donate, reason if not
 */
function canSponsorDonate($conn, $sponsor_id) {
    $stmt = $conn->prepare("
        SELECT s.is_flagged, s.flag_reason, fc.status
        FROM sponsors s
        LEFT JOIN fraud_cases fc ON s.sponsor_id = fc.sponsor_id
            AND fc.status NOT IN ('cleared')
        WHERE s.sponsor_id = ?
    ");
    $stmt->bind_param('i', $sponsor_id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    
    if (!$result) {
        return ['can_donate' => false, 'reason' => 'Sponsor not found'];
    }
    
    // Blocked = cannot donate
    if ($result['status'] === 'blocked') {
        return [
            'can_donate' => false,
            'reason' => 'Account blocked. Please contact support or submit an appeal.'
        ];
    }
    
    // Frozen = cannot donate
    if ($result['status'] === 'frozen') {
        return [
            'can_donate' => false,
            'reason' => 'Account temporarily frozen pending review. You can submit an appeal.'
        ];
    }
    
    // Restricted = can donate but with limits (implement limits elsewhere)
    if ($result['status'] === 'restricted') {
        return [
            'can_donate' => true,
            'restricted' => true,
            'message' => 'Account under review. Donation limits may apply.'
        ];
    }
    
    return ['can_donate' => true];
}

/**
 * Get fraud statistics for dashboard
 * 
 * @return array Stats summary
 */
function getFraudStatistics($conn) {
    $stats = [];
    
    // Total active cases
    $result = $conn->query("
        SELECT COUNT(*) as count FROM fraud_cases 
        WHERE status NOT IN ('cleared', 'blocked')
    ");
    $stats['active_cases'] = $result->fetch_assoc()['count'];
    
    // Pending appeals
    $result = $conn->query("
        SELECT COUNT(*) as count FROM fraud_appeals WHERE status = 'pending'
    ");
    $stats['pending_appeals'] = $result->fetch_assoc()['count'];
    
    // Signals last 7 days
    $result = $conn->query("
        SELECT COUNT(*) as count FROM fraud_signals 
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    ");
    $stats['recent_signals'] = $result->fetch_assoc()['count'];
    
    // High risk sponsors
    $result = $conn->query("
        SELECT COUNT(*) as count FROM sponsor_risk_scores 
        WHERE risk_level IN ('high', 'critical')
    ");
    $stats['high_risk_sponsors'] = $result->fetch_assoc()['count'];
    
    return $stats;
}