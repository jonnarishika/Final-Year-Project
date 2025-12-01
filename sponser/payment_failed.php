<?php
session_start();

// Check if sponsor is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../signup_and_login/login_template.php");
    exit();
}

// Get error message if provided
$error_message = isset($_GET['error']) ? $_GET['error'] : 'Payment could not be processed';

// Get child_id if provided (optional - for Try Again button)
$child_id = isset($_GET['child_id']) ? (int)$_GET['child_id'] : null;

// Sanitize error message for display
$error_message = htmlspecialchars($error_message);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Failed</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #eb3349 0%, #f45c43 100%);
            min-height: 100vh;
            padding: 40px 20px;
            position: relative;
            overflow-x: hidden;
        }

        /* Animated warning aura */
        body::before {
            content: '';
            position: fixed;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(235, 51, 73, 0.3) 0%, transparent 70%);
            animation: pulseWarning 3s ease-in-out infinite;
            z-index: 0;
        }

        @keyframes pulseWarning {
            0%, 100% { transform: scale(1); opacity: 0.4; }
            50% { transform: scale(1.15); opacity: 0.7; }
        }

        .container {
            max-width: 600px;
            width: 100%;
            margin: 0 auto;
            position: relative;
            z-index: 2;
        }

        /* Main error card */
        .error-card {
            background: rgba(255, 255, 255, 0.25);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 2px solid rgba(255, 255, 255, 0.4);
            border-radius: 30px;
            padding: 50px 40px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.2);
            text-align: center;
            animation: shakeIn 0.6s ease-out;
        }

        @keyframes shakeIn {
            0% {
                opacity: 0;
                transform: translateY(30px) rotate(-2deg);
            }
            50% {
                transform: translateY(-10px) rotate(2deg);
            }
            100% {
                opacity: 1;
                transform: translateY(0) rotate(0deg);
            }
        }

        /* Error icon */
        .error-icon {
            width: 100px;
            height: 100px;
            background: rgba(255, 255, 255, 0.9);
            border-radius: 50%;
            margin: 0 auto 30px;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 10px 40px rgba(235, 51, 73, 0.5);
            animation: bounceIn 0.6s ease-out;
        }

        @keyframes bounceIn {
            0% { transform: scale(0); }
            50% { transform: scale(1.1); }
            100% { transform: scale(1); }
        }

        .error-icon svg {
            width: 60px;
            height: 60px;
            stroke: #eb3349;
            stroke-width: 3;
            stroke-linecap: round;
            stroke-linejoin: round;
            fill: none;
            animation: drawX 0.6s ease-out 0.3s both;
        }

        @keyframes drawX {
            0% { stroke-dasharray: 0, 100; }
            100% { stroke-dasharray: 100, 100; }
        }

        h1 {
            font-size: 36px;
            color: #ffffff;
            margin-bottom: 15px;
            font-weight: 700;
            text-shadow: 0 2px 10px rgba(0, 0, 0, 0.15);
        }

        .subtitle {
            font-size: 18px;
            color: rgba(255, 255, 255, 0.95);
            margin-bottom: 30px;
            line-height: 1.6;
        }

        /* Error details */
        .error-details {
            background: rgba(255, 255, 255, 0.15);
            border-radius: 20px;
            padding: 25px;
            margin-bottom: 30px;
            border: 1px solid rgba(255, 255, 255, 0.3);
        }

        .error-message {
            font-size: 15px;
            color: rgba(255, 255, 255, 0.95);
            line-height: 1.6;
            margin-bottom: 15px;
        }

        .error-code {
            font-size: 13px;
            color: rgba(255, 255, 255, 0.8);
            font-family: monospace;
            background: rgba(0, 0, 0, 0.2);
            padding: 10px;
            border-radius: 8px;
            word-break: break-all;
        }

        /* Possible reasons */
        .reasons-section {
            background: rgba(255, 255, 255, 0.1);
            border-radius: 20px;
            padding: 25px;
            margin-bottom: 30px;
            text-align: left;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .reasons-section h3 {
            font-size: 18px;
            color: #ffffff;
            margin-bottom: 15px;
            text-align: center;
        }

        .reason-list {
            list-style: none;
            padding: 0;
        }

        .reason-list li {
            font-size: 14px;
            color: rgba(255, 255, 255, 0.9);
            padding: 10px 0;
            padding-left: 30px;
            position: relative;
            line-height: 1.5;
        }

        .reason-list li::before {
            content: '⚠️';
            position: absolute;
            left: 0;
            font-size: 16px;
        }

        /* Action buttons */
        .action-buttons {
            display: flex;
            gap: 15px;
            margin-top: 30px;
        }

        .btn {
            flex: 1;
            padding: 16px 30px;
            border: none;
            border-radius: 15px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        .btn-primary {
            background: rgba(255, 255, 255, 0.95);
            color: #eb3349;
            box-shadow: 0 4px 15px rgba(255, 255, 255, 0.3);
        }

        .btn-primary:hover {
            background: #ffffff;
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(255, 255, 255, 0.4);
        }

        .btn-secondary {
            background: rgba(255, 255, 255, 0.2);
            color: #ffffff;
            border: 2px solid rgba(255, 255, 255, 0.4);
        }

        .btn-secondary:hover {
            background: rgba(255, 255, 255, 0.3);
            transform: translateY(-2px);
        }

        /* Support section */
        .support-section {
            margin-top: 30px;
            padding: 20px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 15px;
            font-size: 14px;
            color: rgba(255, 255, 255, 0.9);
            line-height: 1.6;
        }

        .support-section a {
            color: #ffffff;
            text-decoration: underline;
            font-weight: 600;
        }

        @media (max-width: 600px) {
            body {
                padding: 20px 15px;
            }

            .error-card {
                padding: 40px 25px;
            }

            h1 {
                font-size: 28px;
            }

            .action-buttons {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="error-card">
            <!-- Error Icon -->
            <div class="error-icon">
                <svg viewBox="0 0 52 52">
                    <line x1="16" y1="16" x2="36" y2="36"/>
                    <line x1="36" y1="16" x2="16" y2="36"/>
                </svg>
            </div>

            <h1>❌ Payment Failed</h1>
            <p class="subtitle">
                Unfortunately, your payment could not be processed. Please try again.
            </p>

            <!-- Error Details -->
            <div class="error-details">
                <div class="error-message">
                    <strong>Error:</strong> <?php echo $error_message; ?>
                </div>
                <div class="error-code">
                    Transaction ID: TXN-<?php echo strtoupper(substr(md5(time()), 0, 12)); ?>
                </div>
            </div>

            <!-- Possible Reasons -->
            <div class="reasons-section">
                <h3>Possible Reasons</h3>
                <ul class="reason-list">
                    <li>Insufficient balance in your account</li>
                    <li>Payment gateway timeout or network issue</li>
                    <li>Incorrect payment details entered</li>
                    <li>Transaction declined by your bank</li>
                    <li>Security verification failed</li>
                </ul>
            </div>

            <!-- Action Buttons -->
            <div class="action-buttons">
                <?php if ($child_id): ?>
                    <a href="sponsor_child.php?child_id=<?php echo $child_id; ?>" class="btn btn-primary">
                        🔄 Try Again
                    </a>
                <?php else: ?>
                    <a href="javascript:history.back()" class="btn btn-primary">
                        🔄 Try Again
                    </a>
                <?php endif; ?>
                <a href="sponser_profile.php" class="btn btn-secondary">
                    ← Back to Dashboard
                </a>
            </div>

            <!-- Support Section -->
            <div class="support-section">
                💬 Need help? Contact our support team at 
                <a href="mailto:support@sponsorlink.com">support@sponsorlink.com</a>
                <br>
                or call us at <strong>+91 1800-XXX-XXXX</strong>
            </div>
        </div>
    </div>
</body>
</html>