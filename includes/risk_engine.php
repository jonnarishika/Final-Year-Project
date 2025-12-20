<?php

require_once __DIR__ . '/../db_config.php';

const FRAUD_WEIGHTS = [
    // Payment Abuse (A)
    'PAYMENT_FAILURE_BURST' => 10,          // 3+ failures in 24h
    'PAYMENT_FAILURE_PATTERN' => 15,        // 5+ failures in 7 days
    'PAYMENT_REUSE_FRAUD' => 30,            // Razorpay ID reused
    'MULTI_CHILD_VELOCITY' => 20,           // Same sponsor → many children quickly
    
    // Amount Anomalies (B)
    'AMOUNT_SPIKE' => 10,                   // 10× usual amount
    'MICRO_DONATION_TESTING' => 15,         // Repeated ₹1-₹10 donations
    'DONATE_REFUND_CYCLE' => 25,            // Rapid donate-refund pattern
    
    // Behavior Velocity (C)
    'BOT_LIKE_BEHAVIOR' => 20,              // >10 actions in 5 mins
    'ODD_HOURS_PATTERN' => 8,               // Repeated odd-hour activity
    'DEVICE_SHARING' => 25,                 // Same IP/device across sponsors
    
    // Content-Based (D)
    'CONTENT_POLICY_VIOLATION' => 15,       // Irrelevant uploads
    'SPAM_BEHAVIOR' => 12,                  // Repeated report spam
    'CONTACT_ATTEMPT' => 30,                // Safety violation
    
    // Child Safety (E)
    'PERSONAL_CONTACT_REQUEST' => 35,       // Severe - contact request
    'COERCION_LANGUAGE' => 40,              // Critical - monetary coercion
    'GROOMING_RISK' => 50,                  // Critical - pattern indicates grooming
    
    // Manual/Staff
    'STAFF_REPORT' => 12                    // Staff-flagged concern
];

/**
 * RISK LEVEL THRESHOLDS
 */
const RISK_THRESHOLDS = [
    'normal' => [0, 19],
    'watch' => [20, 39],
    'review' => [40, 59],
    'high' => [60, 79],
    'critical' => [80, PHP_INT_MAX]
];

/**
 * Calculate risk level from score
 */
function calculateRiskLevel($score) {
    foreach (RISK_THRESHOLDS as $level => $range) {
        if ($score >= $range[0] && $score <= $range[1]) {
            return $level;
        }
    }
    return 'critical';
}

/**
 * Get or initialize sponsor risk score
 */
function getSponsorRiskScore($conn, $sponsor_id) {
    $stmt = $conn->prepare("
        SELECT risk_score, risk_level, last_updated 
        FROM sponsor_risk_scores 
        WHERE sponsor_id = ?
    ");
    $stmt->bind_param('i', $sponsor_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        // Initialize if doesn't exist
        $init_stmt = $conn->prepare("
            INSERT INTO sponsor_risk_scores (sponsor_id, risk_score, risk_level) 
            VALUES (?, 0, 'normal')
        ");
        $init_stmt->bind_param('i', $sponsor_id);
        $init_stmt->execute();
        
        return ['risk_score' => 0, 'risk_level' => 'normal', 'last_updated' => date('Y-m-d H:i:s')];
    }
    
    return $result->fetch_assoc();
}

/**
 * Update sponsor risk score
 */
function updateRiskScore($conn, $sponsor_id, $new_score) {
    $risk_level = calculateRiskLevel($new_score);
    
    $stmt = $conn->prepare("
        UPDATE sponsor_risk_scores 
        SET risk_score = ?, risk_level = ?, last_updated = NOW() 
        WHERE sponsor_id = ?
    ");
    $stmt->bind_param('isi', $new_score, $risk_level, $sponsor_id);
    $stmt->execute();
    
    return ['risk_score' => $new_score, 'risk_level' => $risk_level];
}

/**
 * Add points to risk score
 */
function addRiskPoints($conn, $sponsor_id, $points) {
    $current = getSponsorRiskScore($conn, $sponsor_id);
    $new_score = max(0, $current['risk_score'] + $points);
    return updateRiskScore($conn, $sponsor_id, $new_score);
}

/**
 * 1️⃣ PAYMENT ABUSE DETECTION
 */
function detectPaymentAbuse($conn, $sponsor_id, $donation_id = null) {
    $signals = [];
    
    // A1: Payment failure burst (3+ in 24h)
    $stmt = $conn->prepare("
        SELECT COUNT(*) as failure_count
        FROM donations
        WHERE sponsor_id = ? 
        AND status = 'Failed'
        AND donation_date >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
    ");
    $stmt->bind_param('i', $sponsor_id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    
    if ($result['failure_count'] >= 3) {
        $signals[] = [
            'type' => 'auto_payment_integrity',
            'weight' => FRAUD_WEIGHTS['PAYMENT_FAILURE_BURST'],
            'description' => "{$result['failure_count']} failed payments in last 24 hours"
        ];
    }
    
    // A2: Payment failure pattern (5+ in 7 days)
    $stmt = $conn->prepare("
        SELECT COUNT(*) as failure_count
        FROM donations
        WHERE sponsor_id = ? 
        AND status = 'Failed'
        AND donation_date >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    ");
    $stmt->bind_param('i', $sponsor_id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    
    if ($result['failure_count'] >= 5) {
        $signals[] = [
            'type' => 'auto_payment_integrity',
            'weight' => FRAUD_WEIGHTS['PAYMENT_FAILURE_PATTERN'],
            'description' => "{$result['failure_count']} failed payments in last 7 days - suspicious pattern"
        ];
    }
    
    // A3: Razorpay ID reuse (critical fraud)
    if ($donation_id) {
        $stmt = $conn->prepare("
            SELECT d1.razorpay_payment_id, COUNT(DISTINCT d1.sponsor_id) as sponsor_count
            FROM donations d1
            INNER JOIN donations d2 ON d1.razorpay_payment_id = d2.razorpay_payment_id
            WHERE d2.donation_id = ?
            AND d1.razorpay_payment_id IS NOT NULL
            AND d1.razorpay_payment_id != ''
            GROUP BY d1.razorpay_payment_id
            HAVING sponsor_count > 1
        ");
        $stmt->bind_param('i', $donation_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $data = $result->fetch_assoc();
            $signals[] = [
                'type' => 'auto_payment_integrity',
                'weight' => FRAUD_WEIGHTS['PAYMENT_REUSE_FRAUD'],
                'description' => "Payment ID reused across {$data['sponsor_count']} sponsors - critical fraud indicator"
            ];
        }
    }
    
    // A4: Multi-child velocity (same sponsor → many children quickly)
    $stmt = $conn->prepare("
        SELECT COUNT(DISTINCT child_id) as child_count
        FROM donations
        WHERE sponsor_id = ?
        AND donation_date >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    ");
    $stmt->bind_param('i', $sponsor_id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    
    if ($result['child_count'] >= 5) {
        $signals[] = [
            'type' => 'auto_frequency',
            'weight' => FRAUD_WEIGHTS['MULTI_CHILD_VELOCITY'],
            'description' => "Donated to {$result['child_count']} different children in 7 days"
        ];
    }
    
    return $signals;
}

/**
 * 2️⃣ AMOUNT ANOMALY DETECTION
 */
function detectAmountAnomalies($conn, $sponsor_id, $current_amount = null) {
    $signals = [];
    
    // B1: Calculate sponsor's baseline (median donation)
    $stmt = $conn->prepare("
        SELECT AVG(amount) as avg_amount, MAX(amount) as max_amount
        FROM donations
        WHERE sponsor_id = ?
        AND status = 'Success'
        AND donation_date >= DATE_SUB(NOW(), INTERVAL 90 DAY)
    ");
    $stmt->bind_param('i', $sponsor_id);
    $stmt->execute();
    $baseline = $stmt->get_result()->fetch_assoc();
    
    if ($current_amount && $baseline['avg_amount'] > 0) {
        $spike_ratio = $current_amount / $baseline['avg_amount'];
        
        if ($spike_ratio >= 10) {
            $signals[] = [
                'type' => 'auto_amount_spike',
                'weight' => FRAUD_WEIGHTS['AMOUNT_SPIKE'],
                'description' => "Amount spike: ₹{$current_amount} is " . round($spike_ratio, 1) . "× usual amount"
            ];
        }
    }
    
    // B2: Micro-donation testing (₹1-₹10 repeated)
    $stmt = $conn->prepare("
        SELECT COUNT(*) as micro_count
        FROM donations
        WHERE sponsor_id = ?
        AND amount BETWEEN 1 AND 10
        AND donation_date >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    ");
    $stmt->bind_param('i', $sponsor_id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    
    if ($result['micro_count'] >= 5) {
        $signals[] = [
            'type' => 'auto_amount_spike',
            'weight' => FRAUD_WEIGHTS['MICRO_DONATION_TESTING'],
            'description' => "{$result['micro_count']} micro-donations (₹1-₹10) in 7 days - testing behavior"
        ];
    }
    
    return $signals;
}

/**
 * 3️⃣ VELOCITY / BEHAVIOR DETECTION
 */
function detectBehaviorVelocity($conn, $sponsor_id) {
    $signals = [];
    
    // C1: High frequency in short time (>10 donations in 5 mins)
    $stmt = $conn->prepare("
        SELECT COUNT(*) as rapid_count
        FROM donations
        WHERE sponsor_id = ?
        AND donation_date >= DATE_SUB(NOW(), INTERVAL 5 MINUTE)
    ");
    $stmt->bind_param('i', $sponsor_id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    
    if ($result['rapid_count'] >= 10) {
        $signals[] = [
            'type' => 'auto_frequency',
            'weight' => FRAUD_WEIGHTS['BOT_LIKE_BEHAVIOR'],
            'description' => "{$result['rapid_count']} donations in 5 minutes - bot-like behavior"
        ];
    }
    
    // C2: Odd hours pattern (donations between 2 AM - 5 AM repeatedly)
    $stmt = $conn->prepare("
        SELECT COUNT(*) as odd_hour_count
        FROM donations
        WHERE sponsor_id = ?
        AND HOUR(donation_date) BETWEEN 2 AND 5
        AND donation_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    ");
    $stmt->bind_param('i', $sponsor_id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    
    if ($result['odd_hour_count'] >= 10) {
        $signals[] = [
            'type' => 'auto_frequency',
            'weight' => FRAUD_WEIGHTS['ODD_HOURS_PATTERN'],
            'description' => "{$result['odd_hour_count']} donations during odd hours (2-5 AM) in 30 days"
        ];
    }
    
    return $signals;
}

/**
 * 🔥 MASTER FRAUD CHECK - Run all detections
 */
function runFraudDetection($conn, $sponsor_id, $donation_id = null, $current_amount = null) {
    $all_signals = [];
    
    // Run all detection modules
    $all_signals = array_merge($all_signals, detectPaymentAbuse($conn, $sponsor_id, $donation_id));
    $all_signals = array_merge($all_signals, detectAmountAnomalies($conn, $sponsor_id, $current_amount));
    $all_signals = array_merge($all_signals, detectBehaviorVelocity($conn, $sponsor_id));
    
    // Insert signals and update risk score
    $total_weight = 0;
    
    foreach ($all_signals as $signal) {
        $stmt = $conn->prepare("
            INSERT INTO fraud_signals 
            (sponsor_id, donation_id, signal_type, signal_source, weight, description)
            VALUES (?, ?, ?, 'system', ?, ?)
        ");
        $stmt->bind_param(
            'iisis',
            $sponsor_id,
            $donation_id,
            $signal['type'],
            $signal['weight'],
            $signal['description']
        );
        $stmt->execute();
        
        $total_weight += $signal['weight'];
    }
    
    // Update risk score if signals found
    if ($total_weight > 0) {
        addRiskPoints($conn, $sponsor_id, $total_weight);
    }
    
    return [
        'signals_detected' => count($all_signals),
        'total_weight' => $total_weight,
        'signals' => $all_signals,
        'new_risk' => getSponsorRiskScore($conn, $sponsor_id)
    ];
}

/**
 * 🕐 RISK DECAY - Reduce score over time for clean behavior
 * Should be run via CRON daily/weekly
 */
function applyRiskDecay($conn, $days_lookback = 30, $decay_percentage = 5) {
    // Get sponsors with risk scores who have been clean
    $stmt = $conn->prepare("
        SELECT srs.sponsor_id, srs.risk_score
        FROM sponsor_risk_scores srs
        WHERE srs.risk_score > 0
        AND srs.last_updated <= DATE_SUB(NOW(), INTERVAL ? DAY)
        AND NOT EXISTS (
            SELECT 1 FROM fraud_signals fs
            WHERE fs.sponsor_id = srs.sponsor_id
            AND fs.created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
        )
    ");
    $stmt->bind_param('ii', $days_lookback, $days_lookback);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $decayed_count = 0;
    
    while ($row = $result->fetch_assoc()) {
        $new_score = max(0, $row['risk_score'] - ceil($row['risk_score'] * ($decay_percentage / 100)));
        updateRiskScore($conn, $row['sponsor_id'], $new_score);
        $decayed_count++;
    }
    
    return $decayed_count;
}

/**
 * 🚨 AUTO-CREATE FRAUD CASE if threshold exceeded
 */
function checkAndCreateCase($conn, $sponsor_id, $admin_id = 1) {
    $risk = getSponsorRiskScore($conn, $sponsor_id);
    
    // Auto-create case if risk level is 'review' or higher and no active case exists
    if (in_array($risk['risk_level'], ['review', 'high', 'critical'])) {
        // Check if active case already exists
        $stmt = $conn->prepare("
            SELECT fraud_case_id 
            FROM fraud_cases 
            WHERE sponsor_id = ? 
            AND status NOT IN ('cleared', 'blocked')
        ");
        $stmt->bind_param('i', $sponsor_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            // Create new case
            $summary = "Auto-generated case: Risk score reached {$risk['risk_score']} ({$risk['risk_level']} level)";
            
            $stmt = $conn->prepare("
                INSERT INTO fraud_cases 
                (sponsor_id, opened_by, current_risk_score, summary, status)
                VALUES (?, ?, ?, ?, 'under_review')
            ");
            $stmt->bind_param('iiis', $sponsor_id, $admin_id, $risk['risk_score'], $summary);
            $stmt->execute();
            
            return $conn->insert_id;
        }
    }
    
    return null;
}

/**
 * 📊 GET SPONSOR FRAUD SUMMARY
 */
function getSponsorFraudSummary($conn, $sponsor_id) {
    $risk = getSponsorRiskScore($conn, $sponsor_id);
    
    // Get signal breakdown
    $stmt = $conn->prepare("
        SELECT signal_type, COUNT(*) as count, SUM(weight) as total_weight
        FROM fraud_signals
        WHERE sponsor_id = ?
        GROUP BY signal_type
    ");
    $stmt->bind_param('i', $sponsor_id);
    $stmt->execute();
    $signal_breakdown = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    // Get active case status
    $stmt = $conn->prepare("
        SELECT fraud_case_id, status, created_at
        FROM fraud_cases
        WHERE sponsor_id = ?
        AND status NOT IN ('cleared', 'blocked')
        ORDER BY created_at DESC
        LIMIT 1
    ");
    $stmt->bind_param('i', $sponsor_id);
    $stmt->execute();
    $active_case = $stmt->get_result()->fetch_assoc();
    
    return [
        'risk_score' => $risk['risk_score'],
        'risk_level' => $risk['risk_level'],
        'last_updated' => $risk['last_updated'],
        'signal_breakdown' => $signal_breakdown,
        'active_case' => $active_case,
        'has_active_case' => !empty($active_case)
    ];
}

/**
 * 🔄 RECALCULATE RISK SCORE from scratch
 * Useful after case resolution or appeal
 */
function recalculateRiskScore($conn, $sponsor_id) {
    // Sum all active signals
    $stmt = $conn->prepare("
        SELECT SUM(weight) as total_weight
        FROM fraud_signals
        WHERE sponsor_id = ?
    ");
    $stmt->bind_param('i', $sponsor_id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    
    $new_score = $result['total_weight'] ?? 0;
    return updateRiskScore($conn, $sponsor_id, $new_score);
}