<?php
/**
 * ==============================================
 * FRAUD SYSTEM INTEGRATION TEST
 * ==============================================
 * Run: php test_fraud_workflow.php
 */

require_once __DIR__ . '/../db_config.php';
require_once __DIR__ . '/../includes/fraud_services.php';

echo "\n================ FRAUD SYSTEM TEST START ================\n";

// ---- CONFIG (use existing IDs from your DB) ----
$SPONSOR_ID = 3;        // must exist
$STAFF_ID   = 5;        // role = Staff
$ADMIN_ID   = 1;        // role = Admin

// ------------------------------------------------
// 1️⃣ Initial risk
// ------------------------------------------------
echo "\n[1] Initial Risk\n";
print_r(getSponsorRiskScore($conn, $SPONSOR_ID));

// ------------------------------------------------
// 2️⃣ Staff creates fraud report
// ------------------------------------------------
echo "\n[2] Staff creates report\n";
$response = createStaffReport(
    $conn,
    $SPONSOR_ID,
    $STAFF_ID,
    'Suspicious repeated failed donations'
);
print_r($response);

// ------------------------------------------------
// 3️⃣ Verify staff reports list
// ------------------------------------------------
echo "\n[3] Fetch staff reports\n";
$reports = getStaffReports($conn, $STAFF_ID);
print_r($reports);

// ------------------------------------------------
// 4️⃣ Admin views all signals
// ------------------------------------------------
echo "\n[4] Admin signal overview\n";
$signals = getAllSignals($conn);
print_r($signals);

// ------------------------------------------------
// 5️⃣ Get fraud case details
// ------------------------------------------------
echo "\n[5] Fraud case details\n";
$case = getFraudCaseDetails($conn, $SPONSOR_ID);
print_r($case);

// ------------------------------------------------
// 6️⃣ Admin restricts sponsor
// ------------------------------------------------
echo "\n[6] Admin restricts sponsor\n";
$action = adminTakeAction(
    $conn,
    $SPONSOR_ID,
    $ADMIN_ID,
    'restrict',
    'Multiple suspicious payment failures'
);
print_r($action);

// ------------------------------------------------
// 7️⃣ Check donation enforcement
// ------------------------------------------------
echo "\n[7] Donation enforcement check\n";
print_r(canSponsorDonate($conn, $SPONSOR_ID));

// ------------------------------------------------
// 8️⃣ Sponsor submits appeal
// ------------------------------------------------
echo "\n[8] Sponsor submits appeal\n";
$appeal = submitAppeal(
    $conn,
    $SPONSOR_ID,
    $case['active_case']['fraud_case_id'],
    'My bank declined the payments incorrectly.'
);
print_r($appeal);

// ------------------------------------------------
// 9️⃣ Admin reviews appeal (ACCEPT)
// ------------------------------------------------
echo "\n[9] Admin reviews appeal\n";
$review = reviewAppeal(
    $conn,
    $appeal['appeal_id'],
    $ADMIN_ID,
    'accepted',
    'Evidence supports sponsor explanation'
);
print_r($review);

// ------------------------------------------------
// 🔟 Final risk + flag status
// ------------------------------------------------
echo "\n[10] Final sponsor status\n";
print_r(getSponsorFlagStatus($conn, $SPONSOR_ID));

echo "\n================ FRAUD SYSTEM TEST END ==================\n";
