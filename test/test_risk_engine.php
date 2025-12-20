<?php
/**
 * test_risk_engine.php
 *
 * PURPOSE:
 * Manual + automated test harness for risk_engine.php
 * NO API, NO UI — direct function calls
 *
 * HOW TO USE:
 * 1. Create a test sponsor + donations in DB
 * 2. Update $TEST_SPONSOR_ID below
 * 3. Run: php test_risk_engine.php
 */

require_once __DIR__ . '/../db_config.php';
require_once 'risk_engine.php';



/* ===============================
   CONFIG
   =============================== */

$TEST_SPONSOR_ID = 1;      // ⚠️ change to an existing sponsor
$TEST_DONATION_ID = null; // optional
$TEST_AMOUNT = 5000;      // simulate large donation

echo "\n==============================\n";
echo " FRAUD ENGINE TEST STARTED\n";
echo "==============================\n";

/* ===============================
   1️⃣ BASELINE CHECK
   =============================== */
echo "\n[1] Fetch initial risk score\n";

$initialRisk = getSponsorRiskScore($conn, $TEST_SPONSOR_ID);
print_r($initialRisk);

/* ===============================
   2️⃣ SIMULATE FAILED PAYMENTS
   =============================== */
echo "\n[2] Simulating failed payments\n";

$stmt = $conn->prepare("
    INSERT INTO donations (sponsor_id, child_id, amount, status, donation_date)
    VALUES (?, 1, 100, 'Failed', NOW())
");

for ($i = 0; $i < 3; $i++) {
    $stmt->bind_param('i', $TEST_SPONSOR_ID);
    $stmt->execute();
}

echo "Inserted 3 failed donations\n";

/* ===============================
   3️⃣ RUN FRAUD DETECTION
   =============================== */
echo "\n[3] Running fraud detection\n";

$result = runFraudDetection(
    $conn,
    $TEST_SPONSOR_ID,
    $TEST_DONATION_ID,
    $TEST_AMOUNT
);

print_r($result);

/* ===============================
   4️⃣ CHECK UPDATED RISK
   =============================== */
echo "\n[4] Risk after detection\n";

$updatedRisk = getSponsorRiskScore($conn, $TEST_SPONSOR_ID);
print_r($updatedRisk);

/* ===============================
   5️⃣ AUTO CASE CREATION CHECK
   =============================== */
echo "\n[5] Checking auto case creation\n";

$caseId = checkAndCreateCase($conn, $TEST_SPONSOR_ID);

if ($caseId) {
    echo "Fraud case CREATED with ID: {$caseId}\n";
} else {
    echo "No new fraud case created\n";
}

/* ===============================
   6️⃣ FRAUD SUMMARY
   =============================== */
echo "\n[6] Sponsor fraud summary\n";

$summary = getSponsorFraudSummary($conn, $TEST_SPONSOR_ID);
print_r($summary);

/* ===============================
   7️⃣ APPLY RISK DECAY (SIMULATION)
   =============================== */
echo "\n[7] Applying risk decay simulation\n";

$decayed = applyRiskDecay($conn, 0, 10); // force decay
echo "Risk decayed for {$decayed} sponsor(s)\n";

/* ===============================
   8️⃣ RECALCULATE FROM SCRATCH
   =============================== */
echo "\n[8] Recalculating risk score from scratch\n";

$recalc = recalculateRiskScore($conn, $TEST_SPONSOR_ID);
print_r($recalc);

/* ===============================
   DONE
   =============================== */
echo "\n==============================\n";
echo " FRAUD ENGINE TEST COMPLETE\n";
echo "==============================\n";

$conn->close();
