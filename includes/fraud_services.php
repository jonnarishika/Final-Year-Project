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
 * @param resource $conn Database connection
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
 * Enhanced staff report with severity, categories, and multiple donations
 * 
 * @param resource $conn Database connection
 * @param int $sponsor_id Target sponsor
 * @param int $staff_user_id Staff member creating report
 * @param string $description Reason for report
 * @param array $additional_data Contains severity, fraud_categories, donation_ids
 * @return array Success status and signal_id
 */
function createStaffReportEnhanced($conn, $sponsor_id, $staff_user_id, $description, $additional_data) {
    // Validate staff role
    $stmt = $conn->prepare("SELECT user_role FROM users WHERE user_id = ?");
    $stmt->bind_param('i', $staff_user_id);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    
    if ($user['user_role'] !== 'Staff' && $user['user_role'] !== 'Admin') {
        return ['success' => false, 'message' => 'Unauthorized: Only staff can create reports'];
    }
    
    $conn->begin_transaction();
    
    try {
        // Extract additional data
        $severity = $additional_data['severity'] ?? 'medium';
        $fraud_categories = $additional_data['fraud_categories'] ?? [];
        $donation_ids = $additional_data['donation_ids'] ?? [];
        
        // Calculate risk score based on severity
        $base_weight = FRAUD_WEIGHTS['STAFF_REPORT'];
        $severity_multiplier = [
            'low' => 1.0,
            'medium' => 1.5,
            'high' => 2.5,
            'critical' => 4.0
        ];
        
        $weight = intval($base_weight * $severity_multiplier[$severity]);
        
        // Additional points based on number of categories
        $weight += (count($fraud_categories) * 3);
        
        // Additional points if multiple donations involved
        $weight += (count($donation_ids) * 2);
        
        // Prepare metadata JSON
        $metadata = json_encode([
            'severity' => $severity,
            'fraud_categories' => $fraud_categories,
            'donation_ids' => $donation_ids,
            'category_count' => count($fraud_categories),
            'donation_count' => count($donation_ids),
            'reported_by' => $staff_user_id,
            'report_timestamp' => date('Y-m-d H:i:s')
        ]);
        
        // Insert the main fraud signal
        $signal_type = 'staff_report';
        $signal_source = 'staff';
        $donation_id_primary = !empty($donation_ids) ? $donation_ids[0] : null;
        
        $stmt = $conn->prepare("
            INSERT INTO fraud_signals 
            (sponsor_id, donation_id, signal_type, signal_source, weight, description, created_by, metadata)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->bind_param('iissisis', 
            $sponsor_id, 
            $donation_id_primary, 
            $signal_type, 
            $signal_source, 
            $weight, 
            $description, 
            $staff_user_id,
            $metadata
        );
        $stmt->execute();
        $signal_id = $conn->insert_id;
        
        // Update risk score
        addRiskPoints($conn, $sponsor_id, $weight);
        
        // Get updated risk
        $new_risk = getSponsorRiskScore($conn, $sponsor_id);
        
        // Check if case should be created (for high/critical severity or high risk)
        $case_created = false;
        $case_id = null;
        
        if ($severity === 'critical' || $severity === 'high' || 
            $new_risk['risk_level'] === 'high' || $new_risk['risk_level'] === 'critical') {
            
            // Check if active case already exists
            $stmt = $conn->prepare("
                SELECT fraud_case_id 
                FROM fraud_cases 
                WHERE sponsor_id = ? 
                AND status NOT IN ('cleared', 'blocked')
                LIMIT 1
            ");
            $stmt->bind_param('i', $sponsor_id);
            $stmt->execute();
            $existing_case = $stmt->get_result()->fetch_assoc();
            
            if ($existing_case) {
                // Link to existing case
                $case_id = $existing_case['fraud_case_id'];
                
                // Add note to existing case
                $note = "New staff report added (Severity: " . strtoupper($severity) . ")\n";
                $note .= "Categories: " . implode(', ', $fraud_categories) . "\n";
                $note .= "Description: " . $description;
                addCaseNote($conn, $case_id, $staff_user_id, $note);
                
            } else {
                // Auto-create new fraud case
                $case_title = "Staff Report - " . ucwords(str_replace('_', ' ', $fraud_categories[0] ?? 'Suspicious Activity'));
                if (count($fraud_categories) > 1) {
                    $case_title .= " +" . (count($fraud_categories) - 1) . " more";
                }
                
                $case_description = "AUTO-GENERATED from staff report\n\n";
                $case_description .= "Severity: " . strtoupper($severity) . "\n";
                $case_description .= "Categories Flagged:\n";
                foreach ($fraud_categories as $cat) {
                    $case_description .= "  • " . ucwords(str_replace('_', ' ', $cat)) . "\n";
                }
                if (!empty($donation_ids)) {
                    $case_description .= "\nRelated Donations: " . count($donation_ids) . " transaction(s)\n";
                }
                $case_description .= "\nStaff Notes:\n" . $description;
                
                // Determine priority
                $priority = ($severity === 'critical' || $new_risk['risk_level'] === 'critical') ? 'critical' : 'high';
                $status = 'under_review';
                
                $stmt = $conn->prepare("
                    INSERT INTO fraud_cases 
                    (sponsor_id, opened_by, current_risk_score, summary, status)
                    VALUES (?, ?, ?, ?, ?)
                ");
                $case_summary = $case_title;
                $stmt->bind_param('iiiss', 
                    $sponsor_id, 
                    $staff_user_id, 
                    $new_risk['risk_score'], 
                    $case_summary, 
                    $status
                );
                $stmt->execute();
                $case_id = $conn->insert_id;
                $case_created = true;
                
                // Add initial note
                addCaseNote($conn, $case_id, $staff_user_id, $case_description);
            }
        }
        
        $conn->commit();
        
        return [
            'success' => true,
            'signal_id' => $signal_id,
            'case_created' => $case_created,
            'case_id' => $case_id,
            'weight_added' => $weight,
            'new_risk' => $new_risk,
            'message' => 'Report submitted successfully'
        ];
        
    } catch (Exception $e) {
        $conn->rollback();
        return ['success' => false, 'message' => 'Database error: ' . $e->getMessage()];
    }
}

/**
 * Get all reports created by a specific staff member
 * 
 * @param resource $conn Database connection
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
 * @param resource $conn Database connection
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
 * @param resource $conn Database connection
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
 * @param resource $conn Database connection
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
            AND status NOT IN ('cleared')
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
                (sponsor_id, opened_by, current_risk_score, summary, status, monthly_donation_limit)
                VALUES (?, ?, ?, ?, 'under_review', NULL)
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
        
        // Set monthly limits based on action
        $monthly_limit = null;
        if ($action === 'restrict') {
            $monthly_limit = 3000.00; // ₹3,000/month
        } elseif ($action === 'freeze' || $action === 'block') {
            $monthly_limit = 0.00; // No donations
        }
        // For 'clear', monthly_limit stays NULL (unlimited)
        
        // Update case status AND monthly_donation_limit
        $stmt = $conn->prepare("
            UPDATE fraud_cases 
            SET status = ?, 
                current_risk_score = ?,
                monthly_donation_limit = ?
            WHERE fraud_case_id = ?
        ");
        $risk = getSponsorRiskScore($conn, $sponsor_id);
        $stmt->bind_param('sidi', $new_status, $risk['risk_score'], $monthly_limit, $case_id);
        $stmt->execute();
        
        // Add admin note (MANDATORY AUDIT TRAIL)
        $stmt = $conn->prepare("
            INSERT INTO fraud_case_notes (fraud_case_id, admin_id, note)
            VALUES (?, ?, ?)
        ");
        $limit_text = $monthly_limit !== null ? "₹" . number_format($monthly_limit, 2) : "Unlimited";
        $note = "Action: {$action} | Monthly Limit: {$limit_text} | Justification: {$justification}";
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
                'restrict' => 'Account restricted - Monthly donation limit: ₹3,000',
                'freeze' => 'Account frozen - All donations disabled',
                'block' => 'Account blocked - Access restricted'
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
            'action' => $action,
            'monthly_limit' => $monthly_limit
        ];
        
    } catch (Exception $e) {
        $conn->rollback();
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Add admin note to case (for ongoing documentation)
 * 
 * @param resource $conn Database connection
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
 * @param resource $conn Database connection
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
 * @param resource $conn Database connection
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
 * @param resource $conn Database connection
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
 * @param resource $conn Database connection
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
 * PAYMENT RESTRICTION & ENFORCEMENT
 * ============================================
 */

/**
 * Check if sponsor can donate (simple version - backward compatibility)
 * 
 * @param resource $conn Database connection
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
 * ⭐ NEW FUNCTION - Check if sponsor can donate with detailed status and monthly limits
 * 
 * This is the MAIN enforcement function that checks:
 * - Account status (blocked/frozen/restricted/under_review/cleared)
 * - Monthly donation limits for restricted accounts
 * - Remaining balance in current month
 * 
 * @param resource $conn Database connection
 * @param int $sponsor_id Sponsor ID
 * @return array [
 *   'can_donate' => bool,
 *   'status' => string (normal|restricted|frozen|blocked|under_review),
 *   'reason' => string|null,
 *   'monthly_limit' => float|null,
 *   'remaining_limit' => float|null,
 *   'message' => string
 * ]
 */
function canSponsorDonateDetailed($conn, $sponsor_id) {
    // Get fraud case status - check for cleared cases
    $stmt = $conn->prepare("
        SELECT fc.status, fc.monthly_donation_limit
        FROM fraud_cases fc
        WHERE fc.sponsor_id = ?
        ORDER BY fc.created_at DESC
        LIMIT 1
    ");
    $stmt->bind_param('i', $sponsor_id);
    $stmt->execute();
    $case = $stmt->get_result()->fetch_assoc();
    
    // If no case exists OR case is cleared, allow donations
    if (!$case || $case['status'] === 'cleared') {
        return [
            'can_donate' => true,
            'status' => 'normal',
            'reason' => null,
            'monthly_limit' => null,
            'remaining_limit' => null,
            'message' => 'You can donate freely'
        ];
    }
    
    $status = $case['status'];
    $monthly_limit = $case['monthly_donation_limit'];
    
    // BLOCKED - Cannot donate at all
    if ($status === 'blocked') {
        return [
            'can_donate' => false,
            'status' => 'blocked',
            'reason' => 'Account blocked. Please submit an appeal.',
            'monthly_limit' => 0,
            'remaining_limit' => 0,
            'message' => 'Your account has been blocked. You cannot make donations until this is resolved.'
        ];
    }
    
    // FROZEN - Cannot donate at all
    if ($status === 'frozen') {
        return [
            'can_donate' => false,
            'status' => 'frozen',
            'reason' => 'Account frozen pending investigation.',
            'monthly_limit' => 0,
            'remaining_limit' => 0,
            'message' => 'Your account is temporarily frozen. Donations are disabled.'
        ];
    }
    
    // RESTRICTED - Can donate up to monthly limit
    if ($status === 'restricted') {
        $limit = $monthly_limit ?? 3000.00; // Default ₹3,000
        
        // Calculate current month's donations
        $stmt = $conn->prepare("
            SELECT COALESCE(SUM(amount), 0) as total_donated
            FROM donations
            WHERE sponsor_id = ?
            AND status = 'Success'
            AND MONTH(donation_date) = MONTH(CURRENT_DATE())
            AND YEAR(donation_date) = YEAR(CURRENT_DATE())
        ");
        $stmt->bind_param('i', $sponsor_id);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        
        $total_donated = floatval($result['total_donated']);
        $remaining = max(0, $limit - $total_donated);
        
        if ($remaining <= 0) {
            return [
                'can_donate' => false,
                'status' => 'restricted',
                'reason' => 'Monthly donation limit reached',
                'monthly_limit' => $limit,
                'remaining_limit' => 0,
                'message' => "You have reached your monthly donation limit of ₹" . number_format($limit, 2)
            ];
        }
        
        return [
            'can_donate' => true,
            'status' => 'restricted',
            'reason' => 'Account restricted with monthly limit',
            'monthly_limit' => $limit,
            'remaining_limit' => $remaining,
            'message' => "You have ₹" . number_format($remaining, 2) . " remaining this month (₹" . number_format($limit, 2) . " limit)"
        ];
    }
    
    // UNDER_REVIEW - Can donate freely (just being watched)
    return [
        'can_donate' => true,
        'status' => 'under_review',
        'reason' => 'Account under review',
        'monthly_limit' => null,
        'remaining_limit' => null,
        'message' => 'Your account is under review, but you can continue donating'
    ];
}

/**
 * Validate donation amount against restrictions
 * 
 * @param resource $conn Database connection
 * @param int $sponsor_id Sponsor ID
 * @param float $amount Donation amount to validate
 * @return array ['allowed' => bool, 'message' => string]
 */
function validateDonationAmount($conn, $sponsor_id, $amount) {
    $status = canSponsorDonateDetailed($conn, $sponsor_id);
    
    if (!$status['can_donate']) {
        return [
            'allowed' => false,
            'message' => $status['reason']
        ];
    }
    
    // Check if restricted and amount exceeds remaining limit
    if ($status['status'] === 'restricted' && $status['remaining_limit'] !== null) {
        if ($amount > $status['remaining_limit']) {
            return [
                'allowed' => false,
                'message' => "Donation amount exceeds your remaining monthly limit of ₹" . number_format($status['remaining_limit'], 2)
            ];
        }
    }
    
    return [
        'allowed' => true,
        'message' => 'Donation amount is valid'
    ];
}

/**
 * Get sponsor's current monthly donation total
 * 
 * @param resource $conn Database connection
 * @param int $sponsor_id Sponsor ID
 * @return float Total donated this month
 */
function getMonthlyDonationTotal($conn, $sponsor_id) {
    $stmt = $conn->prepare("
        SELECT COALESCE(SUM(amount), 0) as total
        FROM donations
        WHERE sponsor_id = ?
        AND status = 'Success'
        AND MONTH(donation_date) = MONTH(CURRENT_DATE())
        AND YEAR(donation_date) = YEAR(CURRENT_DATE())
    ");
    $stmt->bind_param('i', $sponsor_id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    
    return floatval($result['total']);
}

/**
 * ============================================
 * UTILITY & STATISTICS FUNCTIONS
 * ============================================
 */

/**
 * Get fraud statistics for dashboard
 * 
 * @param resource $conn Database connection
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

?>