<?php
require_once(__DIR__ . '/../razorpay-php/Razorpay.php');
use Razorpay\Api\Api;
session_start();
require_once __DIR__ . '/../db_config.php';

// Check if sponsor is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../signup_and_login/login_template.php");
    exit();
}

// Validate child_id
if (!isset($_GET['child_id']) || !is_numeric($_GET['child_id'])) {
    die("Invalid child ID");
}

$child_id = (int)$_GET['child_id'];
$user_id = $_SESSION['user_id'];

// FIXED: Fetch the actual sponsor_id from sponsors table
$stmt = $conn->prepare("SELECT sponsor_id FROM sponsors WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$sponsor_result = $stmt->get_result();
$sponsor_data = $sponsor_result->fetch_assoc();

if (!$sponsor_data) {
    die("Sponsor profile not found");
}

$sponsor_id = $sponsor_data['sponsor_id'];

// Fetch child details
$stmt = $conn->prepare("
    SELECT c.*, TIMESTAMPDIFF(YEAR, c.dob, CURDATE()) AS age
    FROM children c
    WHERE c.child_id = ?
");

$stmt->bind_param("i", $child_id);
$stmt->execute();

$result = $stmt->get_result();
$child = $result->fetch_assoc();

if (!$child) {
    die("Child not found");
}

// Fetch sponsor details
$stmt = $conn->prepare("
    SELECT s.*, u.email, u.phone_no 
    FROM sponsors s 
    JOIN users u ON s.user_id = u.user_id 
    WHERE s.user_id = ?
");

$stmt->bind_param("i", $user_id);
$stmt->execute();

$result = $stmt->get_result();
$sponsor = $result->fetch_assoc();

// Handle form submission
$razorpay_order_id = null;
$razorpay_amount = null;
$donation_id = null;
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $amount = filter_input(INPUT_POST, 'amount', FILTER_VALIDATE_FLOAT);
    $csrf_token = $_POST['csrf_token'] ?? '';
    
    // Validate CSRF token
    if ($csrf_token !== ($_SESSION['csrf_token'] ?? '')) {
        $error = "Invalid request";
    } elseif ($amount < 100) {
        $error = "Minimum donation amount is ₹100";
    } else {
        try {
            // Razorpay API credentials
            $api_key = 'rzp_test_RiQdqG3QtpFjdt';
            $api_secret = 'mMhzmW57NeJ7q3AWTUX37wKx';
            $api = new Api($api_key, $api_secret);
            
            // Create Razorpay order
            $orderData = [
                'amount' => $amount * 100, // Convert to paise
                'currency' => 'INR',
                'receipt' => 'TEMP-' . time(),
                'notes' => [
                    'child_id' => $child_id,
                    'sponsor_id' => $sponsor_id
                ]
            ];
            
            $razorpay_order = $api->order->create($orderData);
            
            // Extract order ID and amount properly
            $order_id = $razorpay_order->id;
            $razorpay_order_id = $order_id;
            $razorpay_amount = $razorpay_order->amount;
            
            // Insert donation record with correct sponsor_id
            $stmt = $conn->prepare("
                INSERT INTO donations 
                (sponsor_id, child_id, amount, razorpay_order_id, status, donation_date) 
                VALUES (?, ?, ?, ?, 'Pending', NOW())
            ");

            $stmt->bind_param("iids", 
                $sponsor_id,  // Using correct sponsor_id from sponsors table
                $child_id,
                $amount,
                $order_id
            );

            $stmt->execute();
            $donation_id = $conn->insert_id;
            
        } catch (Exception $e) {
            $error = "Payment processing error: " . $e->getMessage();
        }
    }
}

// Generate CSRF token
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sponsor <?php echo htmlspecialchars($child['first_name']); ?> - Checkout</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #ffeaa7 0%, #fdcb6e 50%, #fab1a0 100%);
            min-height: 100vh;
            padding: 20px;
            position: relative;
            overflow-x: hidden;
        }

        body::before {
            content: '';
            position: fixed;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255, 234, 167, 0.3) 0%, transparent 70%);
            animation: rotate 20s linear infinite;
            z-index: 0;
        }

        @keyframes rotate {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            position: relative;
            z-index: 1;
        }

        .header {
            background: rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.3);
            border-radius: 20px;
            padding: 20px 30px;
            margin-bottom: 30px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
        }

        .header-content {
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .back-btn {
            background: rgba(255, 255, 255, 0.3);
            border: none;
            padding: 10px 20px;
            border-radius: 10px;
            cursor: pointer;
            font-size: 16px;
            display: flex;
            align-items: center;
            gap: 10px;
            transition: all 0.3s;
        }

        .back-btn:hover {
            background: rgba(255, 255, 255, 0.5);
            transform: translateX(-5px);
        }

        h1 {
            font-size: 32px;
            color: #2d3436;
            font-weight: 600;
        }

        .main-content {
            display: grid;
            grid-template-columns: 1fr 400px;
            gap: 30px;
        }

        .glass-card {
            background: rgba(255, 255, 255, 0.2);
            backdrop-filter: blur(15px);
            -webkit-backdrop-filter: blur(15px);
            border: 1px solid rgba(255, 255, 255, 0.4);
            border-radius: 20px;
            padding: 30px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
        }

        .child-info {
            display: flex;
            gap: 25px;
            margin-bottom: 30px;
        }

        .child-photo {
            width: 150px;
            height: 150px;
            border-radius: 15px;
            object-fit: cover;
            border: 3px solid rgba(255, 255, 255, 0.5);
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
        }

        .child-details h2 {
            color: #2d3436;
            margin-bottom: 10px;
        }

        .child-meta {
            display: flex;
            gap: 15px;
            margin: 10px 0;
        }

        .meta-item {
            background: rgba(255, 255, 255, 0.3);
            padding: 8px 15px;
            border-radius: 8px;
            font-size: 14px;
            color: #2d3436;
        }

        .section-title {
            font-size: 20px;
            color: #2d3436;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .section-number {
            background: rgba(255, 255, 255, 0.4);
            width: 30px;
            height: 30px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
        }

        .form-section {
            margin-bottom: 30px;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin-bottom: 15px;
        }

        .form-group {
            margin-bottom: 15px;
        }

        label {
            display: block;
            color: #2d3436;
            font-weight: 500;
            margin-bottom: 8px;
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        input[type="number"],
        input[type="text"],
        input[type="email"] {
            width: 100%;
            padding: 15px;
            border: 1px solid rgba(255, 255, 255, 0.3);
            border-radius: 10px;
            background: rgba(255, 255, 255, 0.4);
            font-size: 16px;
            color: #2d3436;
            transition: all 0.3s;
        }

        input[type="number"]:focus,
        input[type="text"]:focus,
        input[type="email"]:focus {
            outline: none;
            border-color: rgba(255, 255, 255, 0.6);
            background: rgba(255, 255, 255, 0.5);
        }

        input[readonly] {
            background: rgba(255, 255, 255, 0.2);
            cursor: not-allowed;
        }

        .amount-suggestions {
            display: flex;
            gap: 10px;
            margin-top: 10px;
        }

        .amount-btn {
            flex: 1;
            padding: 12px;
            border: 2px solid rgba(255, 255, 255, 0.4);
            border-radius: 10px;
            background: rgba(255, 255, 255, 0.2);
            cursor: pointer;
            font-weight: 600;
            color: #2d3436;
            transition: all 0.3s;
        }

        .amount-btn:hover {
            background: rgba(255, 255, 255, 0.4);
            transform: translateY(-2px);
        }

        .amount-btn.active {
            background: rgba(255, 255, 255, 0.5);
            border-color: rgba(255, 255, 255, 0.7);
        }

        .order-summary h3 {
            color: #2d3436;
            margin-bottom: 20px;
        }

        .summary-item {
            display: flex;
            justify-content: space-between;
            padding: 12px 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.3);
            color: #2d3436;
        }

        .summary-total {
            font-size: 24px;
            font-weight: 700;
            margin-top: 15px;
            padding-top: 15px;
            border-top: 2px solid rgba(255, 255, 255, 0.5);
        }

        .checkout-btn {
            width: 100%;
            padding: 18px;
            background: linear-gradient(135deg, #6c5ce7, #a29bfe);
            border: none;
            border-radius: 12px;
            color: white;
            font-size: 18px;
            font-weight: 600;
            cursor: pointer;
            margin-top: 20px;
            transition: all 0.3s;
            box-shadow: 0 4px 15px rgba(108, 92, 231, 0.4);
        }

        .checkout-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(108, 92, 231, 0.5);
        }

        .checkout-btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }

        .agreement {
            display: flex;
            align-items: start;
            gap: 10px;
            margin-top: 15px;
            font-size: 13px;
            color: #2d3436;
        }

        .agreement input[type="checkbox"] {
            margin-top: 3px;
        }

        .error {
            background: rgba(255, 107, 107, 0.2);
            border: 1px solid rgba(255, 107, 107, 0.4);
            color: #d63031;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
        }

        @media (max-width: 968px) {
            .main-content {
                grid-template-columns: 1fr;
            }
            
            .child-info {
                flex-direction: column;
                align-items: center;
                text-align: center;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="header-content">
                <button class="back-btn" onclick="history.back()">
                    ← Back
                </button>
                <h1>Checkout</h1>
                <div></div>
            </div>
        </div>

        <?php if (isset($error)): ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <div class="main-content">
            <div class="glass-card">
                <div class="child-info">
                    <img src="<?php echo htmlspecialchars($child['profile_picture'] ?: 'default-child.jpg'); ?>" 
                         alt="<?php echo htmlspecialchars($child['first_name']); ?>" 
                         class="child-photo">
                    <div class="child-details">
                        <h2><?php echo htmlspecialchars($child['first_name'] . ' ' . $child['last_name']); ?></h2>
                        <div class="child-meta">
                            <span class="meta-item">Age: <?php echo $child['age']; ?> years</span>
                            <span class="meta-item"><?php echo htmlspecialchars($child['gender']); ?></span>
                        </div>
                        <?php if ($child['about_me']): ?>
                            <p style="margin-top: 15px; color: #2d3436; line-height: 1.6;">
                                <?php echo htmlspecialchars($child['about_me']); ?>
                            </p>
                        <?php endif; ?>
                    </div>
                </div>

                <form method="POST" id="donationForm">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    
                    <div class="form-section">
                        <div class="section-title">
                            <span class="section-number">1</span>
                            Donation Amount
                        </div>
                        <div class="form-group">
                            <label>Amount (INR)</label>
                            <input type="number" 
                                   name="amount" 
                                   id="amount" 
                                   min="100" 
                                   step="1" 
                                   placeholder="Enter amount"
                                   required>
                            <div class="amount-suggestions">
                                <button type="button" class="amount-btn" onclick="setAmount(500)">₹500</button>
                                <button type="button" class="amount-btn" onclick="setAmount(1000)">₹1000</button>
                                <button type="button" class="amount-btn" onclick="setAmount(2500)">₹2500</button>
                                <button type="button" class="amount-btn" onclick="setAmount(5000)">₹5000</button>
                            </div>
                        </div>
                    </div>

                    <div class="form-section">
                        <div class="section-title">
                            <span class="section-number">2</span>
                            Donor Information
                        </div>
                        <div class="form-group">
                            <label>Full Name</label>
                            <input type="text" 
                                   id="donor_name"
                                   value="<?php echo htmlspecialchars($sponsor['first_name'] . ' ' . $sponsor['last_name']); ?>" 
                                   readonly>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label>Email</label>
                                <input type="email" 
                                       id="donor_email"
                                       value="<?php echo htmlspecialchars($sponsor['email']); ?>" 
                                       readonly>
                            </div>
                            <div class="form-group">
                                <label>Phone</label>
                                <input type="text" 
                                       id="donor_phone"
                                       value="<?php echo htmlspecialchars($sponsor['phone_no']); ?>" 
                                       readonly>
                            </div>
                        </div>
                    </div>
                </form>
            </div>

            <div class="glass-card">
                <h3>Donation Summary</h3>
                <div class="summary-item">
                    <span>Child:</span>
                    <strong><?php echo htmlspecialchars($child['first_name']); ?></strong>
                </div>
                <div class="summary-item">
                    <span>Donation Amount:</span>
                    <strong id="summaryAmount">₹0</strong>
                </div>
                <div class="summary-item summary-total">
                    <span>Total:</span>
                    <span id="summaryTotal">₹0</span>
                </div>

                <button type="submit" form="donationForm" class="checkout-btn" id="payBtn">
                    Proceed to Pay →
                </button>

                <div class="agreement">
                    <input type="checkbox" id="agreeTerms" required>
                    <label for="agreeTerms" style="text-transform: none; font-weight: normal;">
                        By confirming this order, I accept the terms of the user agreement
                    </label>
                </div>
            </div>
        </div>
    </div>

    <script src="https://checkout.razorpay.com/v1/checkout.js"></script>
    <script>
        let razorpayOrderId = <?php echo $razorpay_order_id ? "'" . htmlspecialchars($razorpay_order_id) . "'" : "null"; ?>;
        let razorpayAmount = <?php echo $razorpay_amount ? $razorpay_amount : "null"; ?>;
        let apiKey = '<?php echo 'rzp_test_RiQdqG3QtpFjdt'; ?>';

        function setAmount(value) {
            document.getElementById('amount').value = value;
            updateSummary();
            
            document.querySelectorAll('.amount-btn').forEach(btn => {
                btn.classList.remove('active');
            });
            event.target.classList.add('active');
        }

        function updateSummary() {
            const amount = document.getElementById('amount').value || 0;
            document.getElementById('summaryAmount').textContent = '₹' + amount;
            document.getElementById('summaryTotal').textContent = '₹' + amount;
        }

        document.getElementById('amount').addEventListener('input', updateSummary);

        // Handle form submission
        document.getElementById('donationForm').addEventListener('submit', function(e) {
            if (!document.getElementById('agreeTerms').checked) {
                alert('Please accept the terms and conditions');
                e.preventDefault();
                return;
            }

            // Allow first POST to go to PHP to create order
            if (razorpayOrderId) {
                e.preventDefault();
                openRazorpayPayment();
            }
        });

        function openRazorpayPayment() {
            var options = {
                key: apiKey,
                amount: razorpayAmount,
                currency: 'INR',
                name: 'Child Sponsorship',
                description: 'Donation for <?php echo htmlspecialchars($child['first_name']); ?>',
                order_id: razorpayOrderId,
                prefill: {
                    name: document.getElementById('donor_name').value,
                    email: document.getElementById('donor_email').value,
                    contact: document.getElementById('donor_phone').value
                },
                theme: {
                    color: '#6c5ce7'
                },
                handler: function(response) {
                    // Payment successful - redirect to thank_you page
                    var form = document.createElement('form');
                    form.method = 'GET';
                    form.action = 'thank_you.php';
                    
                    var paymentIdInput = document.createElement('input');
                    paymentIdInput.type = 'hidden';
                    paymentIdInput.name = 'payment_id';
                    paymentIdInput.value = response.razorpay_payment_id;
                    form.appendChild(paymentIdInput);
                    
                    var orderIdInput = document.createElement('input');
                    orderIdInput.type = 'hidden';
                    orderIdInput.name = 'order_id';
                    orderIdInput.value = response.razorpay_order_id;
                    form.appendChild(orderIdInput);
                    
                    var signatureInput = document.createElement('input');
                    signatureInput.type = 'hidden';
                    signatureInput.name = 'signature';
                    signatureInput.value = response.razorpay_signature;
                    form.appendChild(signatureInput);
                    
                    var childIdInput = document.createElement('input');
                    childIdInput.type = 'hidden';
                    childIdInput.name = 'child_id';
                    childIdInput.value = '<?php echo $child_id; ?>';
                    form.appendChild(childIdInput);
                    
                    document.body.appendChild(form);
                    form.submit();
                },
                modal: {
                    ondismiss: function() {
                        window.location.href = 'payment_failed.php?error=Payment cancelled by user&child_id=<?php echo $child_id; ?>';
                    }
                }
            };

            var rzp1 = new Razorpay(options);
            
            rzp1.on('payment.failed', function(response) {
                var errorMsg = response.error.description || 'Payment failed';
                var errorReason = response.error.reason || '';
                window.location.href = 'payment_failed.php?error=' + encodeURIComponent(errorMsg) + 
                                      '&reason=' + encodeURIComponent(errorReason) + 
                                      '&child_id=<?php echo $child_id; ?>';
            });

            rzp1.open();
        }
    </script>
</body>
</html>