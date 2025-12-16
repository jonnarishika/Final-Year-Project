<?php
/**
 * SMTP Debug Script - Tests your email configuration
 * This will help identify why emails aren't being sent
 * 
 * Location: DASHBOARDS/test_smtp_debug.php
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/signup_and_login/email_config.php';
require_once __DIR__ . '/vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// ========================================
// CONFIGURATION
// ========================================
define('TEST_EMAIL', 'polisettigeetika123@gmail.com'); // Your test email

echo "<!DOCTYPE html>
<html>
<head>
    <title>SMTP Debug Test</title>
    <style>
        body { font-family: monospace; padding: 20px; background: #1a1a1a; color: #0f0; }
        .success { color: #0f0; }
        .error { color: #f00; }
        .warning { color: #ff0; }
        .info { color: #0af; }
        h1 { color: #fff; border-bottom: 2px solid #0f0; padding-bottom: 10px; }
        h2 { color: #0af; margin-top: 30px; }
        .box { background: #2a2a2a; padding: 15px; margin: 10px 0; border-left: 4px solid #0f0; }
        .error-box { border-left-color: #f00; }
        pre { background: #000; padding: 10px; overflow-x: auto; }
    </style>
</head>
<body>";

echo "<h1>🔍 SMTP Debug & Test Script</h1>";

// ========================================
// 1. CHECK PHP CONFIGURATION
// ========================================
echo "<h2>1️⃣ PHP Configuration Check</h2>";
echo "<div class='box'>";

// Check if OpenSSL is enabled
if (extension_loaded('openssl')) {
    echo "<span class='success'>✅ OpenSSL Extension: ENABLED</span><br>";
} else {
    echo "<span class='error'>❌ OpenSSL Extension: DISABLED (Required for SMTP)</span><br>";
}

// Check if sockets are enabled
if (function_exists('fsockopen')) {
    echo "<span class='success'>✅ Socket Functions: ENABLED</span><br>";
} else {
    echo "<span class='error'>❌ Socket Functions: DISABLED (Required for SMTP)</span><br>";
}

// Check allow_url_fopen
$allow_url_fopen = ini_get('allow_url_fopen');
echo "<span class='info'>ℹ️  allow_url_fopen: " . ($allow_url_fopen ? 'ON' : 'OFF') . "</span><br>";

echo "</div>";

// ========================================
// 2. CHECK SMTP CONFIGURATION
// ========================================
echo "<h2>2️⃣ SMTP Configuration</h2>";
echo "<div class='box'>";

$config_complete = true;

if (defined('SMTP_HOST')) {
    echo "<span class='success'>✅ SMTP_HOST: " . SMTP_HOST . "</span><br>";
} else {
    echo "<span class='error'>❌ SMTP_HOST: NOT DEFINED</span><br>";
    $config_complete = false;
}

if (defined('SMTP_PORT')) {
    echo "<span class='success'>✅ SMTP_PORT: " . SMTP_PORT . "</span><br>";
} else {
    echo "<span class='error'>❌ SMTP_PORT: NOT DEFINED</span><br>";
    $config_complete = false;
}

if (defined('SMTP_USERNAME')) {
    echo "<span class='success'>✅ SMTP_USERNAME: " . SMTP_USERNAME . "</span><br>";
} else {
    echo "<span class='error'>❌ SMTP_USERNAME: NOT DEFINED</span><br>";
    $config_complete = false;
}

if (defined('SMTP_PASSWORD')) {
    $masked = str_repeat('*', strlen(SMTP_PASSWORD) - 4) . substr(SMTP_PASSWORD, -4);
    echo "<span class='success'>✅ SMTP_PASSWORD: " . $masked . "</span><br>";
} else {
    echo "<span class='error'>❌ SMTP_PASSWORD: NOT DEFINED</span><br>";
    $config_complete = false;
}

if (defined('SMTP_FROM_EMAIL')) {
    echo "<span class='success'>✅ SMTP_FROM_EMAIL: " . SMTP_FROM_EMAIL . "</span><br>";
} else {
    echo "<span class='error'>❌ SMTP_FROM_EMAIL: NOT DEFINED</span><br>";
    $config_complete = false;
}

if (!$config_complete) {
    echo "<br><span class='error'>⚠️  SMTP Configuration is incomplete!</span>";
}

echo "</div>";

// ========================================
// 3. TEST SMTP CONNECTION
// ========================================
echo "<h2>3️⃣ SMTP Connection Test</h2>";
echo "<div class='box'>";

if ($config_complete) {
    echo "<span class='info'>Testing connection to " . SMTP_HOST . ":" . SMTP_PORT . "...</span><br><br>";
    
    $mail = new PHPMailer(true);
    
    try {
        // Enable verbose debug output
        $mail->SMTPDebug = 3; // Detailed debug
        $mail->Debugoutput = function($str, $level) {
            echo "<pre style='margin: 5px 0; color: #0af;'>$str</pre>";
        };
        
        // Server settings
        $mail->isSMTP();
        $mail->Host = SMTP_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = SMTP_USERNAME;
        $mail->Password = SMTP_PASSWORD;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = SMTP_PORT;
        
        // Try to connect
        if ($mail->smtpConnect()) {
            echo "<br><span class='success'>✅ SMTP Connection: SUCCESS</span><br>";
            echo "<span class='info'>Server is reachable and credentials are valid</span><br>";
            $mail->smtpClose();
        } else {
            echo "<br><span class='error'>❌ SMTP Connection: FAILED</span><br>";
        }
        
    } catch (Exception $e) {
        echo "<br><div class='box error-box'>";
        echo "<span class='error'>❌ Connection Error:</span><br>";
        echo "<pre>" . htmlspecialchars($e->getMessage()) . "</pre>";
        echo "</div>";
    }
} else {
    echo "<span class='warning'>⚠️  Skipped - Configuration incomplete</span>";
}

echo "</div>";

// ========================================
// 4. SEND TEST EMAIL
// ========================================
echo "<h2>4️⃣ Test Email Send</h2>";
echo "<div class='box'>";

if ($config_complete) {
    echo "<span class='info'>Sending test email to: " . TEST_EMAIL . "</span><br><br>";
    
    $mail = new PHPMailer(true);
    
    try {
        // Enable debug
        $mail->SMTPDebug = 2;
        $mail->Debugoutput = function($str, $level) {
            echo "<pre style='margin: 5px 0; color: #0af;'>$str</pre>";
        };
        
        // Server settings
        $mail->isSMTP();
        $mail->Host = SMTP_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = SMTP_USERNAME;
        $mail->Password = SMTP_PASSWORD;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = SMTP_PORT;
        
        // Recipients
        $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
        $mail->addAddress(TEST_EMAIL, 'Test Recipient');
        $mail->addReplyTo(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
        
        // Content
        $mail->isHTML(true);
        $mail->CharSet = 'UTF-8';
        $mail->Subject = '🧪 SMTP Test - ' . date('Y-m-d H:i:s');
        $mail->Body = '
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: #4CAF50; color: white; padding: 20px; text-align: center; }
                .content { background: #f9f9f9; padding: 20px; }
                .footer { text-align: center; padding: 20px; color: #666; font-size: 12px; }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <h1>✅ SMTP Test Successful!</h1>
                </div>
                <div class="content">
                    <h2>Test Details</h2>
                    <p><strong>Sent At:</strong> ' . date('Y-m-d H:i:s') . '</p>
                    <p><strong>SMTP Server:</strong> ' . SMTP_HOST . '</p>
                    <p><strong>Port:</strong> ' . SMTP_PORT . '</p>
                    <p><strong>From:</strong> ' . SMTP_FROM_EMAIL . '</p>
                    <p><strong>To:</strong> ' . TEST_EMAIL . '</p>
                    
                    <h3>If you received this email:</h3>
                    <ul>
                        <li>✅ Your SMTP configuration is correct</li>
                        <li>✅ PHPMailer is working properly</li>
                        <li>✅ Emails should be delivered successfully</li>
                    </ul>
                    
                    <h3>Next Steps:</h3>
                    <ol>
                        <li>Check that all email templates exist</li>
                        <li>Test the actual notification system</li>
                        <li>Monitor the notification_log table</li>
                    </ol>
                </div>
                <div class="footer">
                    <p>This is an automated test email from your sponsorship system</p>
                </div>
            </div>
        </body>
        </html>
        ';
        
        $mail->AltBody = 'SMTP Test Email - If you received this, your email system is working correctly!';
        
        // Send
        $mail->send();
        
        echo "<br><div class='box'>";
        echo "<span class='success'>✅✅✅ EMAIL SENT SUCCESSFULLY! ✅✅✅</span><br><br>";
        echo "<span class='info'>Check your inbox at: " . TEST_EMAIL . "</span><br>";
        echo "<span class='warning'>⚠️  Don't forget to check SPAM folder!</span><br>";
        echo "</div>";
        
    } catch (Exception $e) {
        echo "<br><div class='box error-box'>";
        echo "<span class='error'>❌ Email sending failed:</span><br>";
        echo "<pre>" . htmlspecialchars($e->getMessage()) . "</pre>";
        echo "<br><span class='warning'>Common Issues:</span><br>";
        echo "<ul>";
        echo "<li>Wrong SMTP password</li>";
        echo "<li>Less secure app access disabled (Gmail)</li>";
        echo "<li>Firewall blocking port " . SMTP_PORT . "</li>";
        echo "<li>2-Factor Authentication enabled without App Password</li>";
        echo "<li>Wrong SMTP host or port</li>";
        echo "</ul>";
        echo "</div>";
    }
} else {
    echo "<span class='warning'>⚠️  Skipped - Configuration incomplete</span>";
}

echo "</div>";

// ========================================
// 5. COMMON ISSUES & SOLUTIONS
// ========================================
echo "<h2>5️⃣ Common Issues & Solutions</h2>";

echo "<div class='box'>";
echo "<h3 style='color: #0af;'>Gmail Users:</h3>";
echo "<ol>";
echo "<li>Enable 'Less secure app access' OR use an App Password</li>";
echo "<li>Go to: <a href='https://myaccount.google.com/security' target='_blank' style='color: #0f0;'>Google Account Security</a></li>";
echo "<li>Turn on 2-Step Verification</li>";
echo "<li>Generate an App Password</li>";
echo "<li>Use the App Password in your email_config.php</li>";
echo "</ol>";
echo "</div>";

echo "<div class='box'>";
echo "<h3 style='color: #0af;'>Other Email Providers:</h3>";
echo "<ul>";
echo "<li><strong>Outlook/Hotmail:</strong> smtp.office365.com:587</li>";
echo "<li><strong>Yahoo:</strong> smtp.mail.yahoo.com:587</li>";
echo "<li><strong>SendGrid:</strong> smtp.sendgrid.net:587</li>";
echo "<li><strong>Mailgun:</strong> smtp.mailgun.org:587</li>";
echo "</ul>";
echo "</div>";

echo "<div class='box'>";
echo "<h3 style='color: #0af;'>Firewall/Server Issues:</h3>";
echo "<ul>";
echo "<li>Check if port 587 is open: <code>telnet " . (defined('SMTP_HOST') ? SMTP_HOST : 'smtp.gmail.com') . " 587</code></li>";
echo "<li>Try port 465 with SMTPSecure = 'ssl' instead</li>";
echo "<li>Contact your hosting provider</li>";
echo "</ul>";
echo "</div>";

// ========================================
// 6. RECOMMENDED EMAIL_CONFIG.PHP
// ========================================
echo "<h2>6️⃣ Recommended email_config.php Configuration</h2>";
echo "<div class='box'>";
echo "<pre style='background: #000; color: #0f0; padding: 15px;'>";
echo htmlspecialchars("<?php
/**
 * Email Configuration for PHPMailer
 * For Gmail: Use App Password, not your regular password!
 */

// SMTP Server Settings
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USERNAME', 'your-email@gmail.com');
define('SMTP_PASSWORD', 'your-16-digit-app-password'); // ⚠️ App Password, NOT regular password!

// Email Headers
define('SMTP_FROM_EMAIL', 'your-email@gmail.com');
define('SMTP_FROM_NAME', 'Your NGO Name');

// Alternative for Outlook:
// define('SMTP_HOST', 'smtp.office365.com');
// define('SMTP_PORT', 587);

// Alternative for custom SMTP:
// define('SMTP_HOST', 'mail.yourdomain.com');
// define('SMTP_PORT', 465); // Use with SMTPSecure = 'ssl'
?>");
echo "</pre>";
echo "</div>";

echo "<h2 style='color: #0f0;'>✅ Debug Complete</h2>";
echo "<p style='color: #fff;'>If the test email was sent successfully, your email system is working correctly!</p>";

echo "</body></html>";
?>