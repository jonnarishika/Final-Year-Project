<?php
/**
 * EmailSender Class - FIXED VERSION WITH DEBUG
 * Handles all email notifications for the sponsorship system
 * 
 * Location: email_system/class.EmailSender.php
 */

require_once __DIR__ . '/../signup_and_login/email_config.php';
require_once __DIR__ . '/../db_config.php';
require_once __DIR__ . '/../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class EmailSender {
    
    private $conn;
    private $templatePath;
    private $debugMode = true; // ⚠️ Set to false in production
    
    public function __construct() {
        global $conn;
        $this->conn = $conn;
        $this->templatePath = __DIR__ . '/templates/';
    }
    
    /**
     * Core email sending method with improved error handling
     */
    private function sendEmail($to, $toName, $subject, $templateFile, $variables) {
        try {
            // Load email template
            $templatePath = $this->templatePath . $templateFile;
            
            if (!file_exists($templatePath)) {
                throw new Exception("Template file not found: $templateFile at $templatePath");
            }
            
            $htmlContent = file_get_contents($templatePath);
            
            // Replace placeholders with actual values
            foreach ($variables as $key => $value) {
                $htmlContent = str_replace('{{' . $key . '}}', $value, $htmlContent);
            }
            
            // Create PHPMailer instance
            $mail = new PHPMailer(true);
            
            // ⚠️ DEBUG MODE - Set to 0 in production
            if ($this->debugMode) {
                $mail->SMTPDebug = 2; // Show detailed debug output
                $mail->Debugoutput = function($str, $level) {
                    error_log("PHPMailer Debug: " . $str);
                };
            } else {
                $mail->SMTPDebug = 0;
            }
            
            // Server settings
            $mail->isSMTP();
            $mail->Host = SMTP_HOST;
            $mail->SMTPAuth = true;
            $mail->Username = SMTP_USERNAME;
            $mail->Password = SMTP_PASSWORD;
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = SMTP_PORT;
            
            // Set timeout (important for slow connections)
            $mail->Timeout = 30;
            
            // Recipients
            $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
            $mail->addAddress($to, $toName);
            $mail->addReplyTo(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
            
            // Content
            $mail->isHTML(true);
            $mail->CharSet = 'UTF-8';
            $mail->Subject = $subject;
            $mail->Body = $htmlContent;
            $mail->AltBody = strip_tags($htmlContent);
            
            // Send email
            $result = $mail->send();
            
            if ($this->debugMode) {
                error_log("✅ Email sent successfully to: $to");
                error_log("Subject: $subject");
                error_log("Template: $templateFile");
            }
            
            return true;
            
        } catch (Exception $e) {
            // Detailed error logging
            $errorMsg = "Email sending failed: " . $e->getMessage();
            error_log("❌ " . $errorMsg);
            error_log("To: $to");
            error_log("Subject: $subject");
            error_log("Template: $templateFile");
            error_log("Full trace: " . $e->getTraceAsString());
            
            // Check specific error types
            if (strpos($e->getMessage(), 'Could not authenticate') !== false) {
                error_log("⚠️  SMTP Authentication failed - Check username/password");
            }
            if (strpos($e->getMessage(), 'connect') !== false) {
                error_log("⚠️  SMTP Connection failed - Check host/port/firewall");
            }
            
            return false;
        }
    }
    
    /**
     * Check if notification already sent (prevent duplicates)
     */
    private function isNotificationSent($sponsor_id, $child_id, $notification_type, $event_id = null, $event_date = null) {
        $query = "SELECT log_id FROM notification_log 
                  WHERE sponsor_id = ? 
                  AND child_id = ? 
                  AND notification_type = ?";
        
        $params = [$sponsor_id, $child_id, $notification_type];
        $types = "iis";
        
        if ($event_id !== null) {
            $query .= " AND event_id = ?";
            $params[] = $event_id;
            $types .= "i";
        }
        
        if ($event_date !== null) {
            $query .= " AND event_date = ?";
            $params[] = $event_date;
            $types .= "s";
        }
        
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $exists = $result->num_rows > 0;
        
        if ($exists && $this->debugMode) {
            error_log("⏭️  Skipping duplicate notification: $notification_type for sponsor $sponsor_id, child $child_id");
        }
        
        return $exists;
    }
    
    /**
     * Log notification in database
     */
    private function logNotification($sponsor_id, $child_id, $notification_type, $event_id = null, $event_date = null, $status = 'sent', $error_message = null) {
        $query = "INSERT INTO notification_log 
                  (sponsor_id, child_id, notification_type, event_id, event_date, delivery_status, error_message) 
                  VALUES (?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("iisisss", $sponsor_id, $child_id, $notification_type, $event_id, $event_date, $status, $error_message);
        
        try {
            $stmt->execute();
            if ($this->debugMode) {
                error_log("📝 Logged notification: $notification_type ($status) for sponsor $sponsor_id");
            }
            return true;
        } catch (Exception $e) {
            // If duplicate entry (unique key constraint), that's okay
            if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
                if ($this->debugMode) {
                    error_log("ℹ️  Duplicate log entry (ignored): $notification_type for sponsor $sponsor_id");
                }
                return true;
            }
            error_log("Failed to log notification: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Send Birthday Reminder Email
     */
    public function sendBirthdayEmail($sponsor, $child, $birthday_date) {
        if ($this->isNotificationSent($sponsor['user_id'], $child['child_id'], 'birthday', null, $birthday_date)) {
            return true;
        }
        
        $age = $this->calculateAge($child['dob']) + 1;
        $days_until = $this->getDaysUntil($birthday_date);
        
        $variables = [
            'sponsor_name' => $sponsor['first_name'] . ' ' . $sponsor['last_name'],
            'child_name' => $child['first_name'] . ' ' . $child['last_name'],
            'child_first_name' => $child['first_name'],
            'birthday_date' => date('F j, Y', strtotime($birthday_date)),
            'days_until' => $days_until,
            'age' => $age,
            'ngo_name' => SMTP_FROM_NAME,
            'dashboard_link' => $this->getDashboardLink('sponsor', $child['child_id']),
            'unsubscribe_link' => $this->getUnsubscribeLink($sponsor['user_id'])
        ];
        
        $subject = "🎂 Reminder: {$child['first_name']}'s Birthday is in {$days_until} Days!";
        
        $success = $this->sendEmail(
            $sponsor['email'],
            $sponsor['first_name'] . ' ' . $sponsor['last_name'],
            $subject,
            'email_birthday.html',
            $variables
        );
        
        $status = $success ? 'sent' : 'failed';
        $error = $success ? null : 'Email sending failed';
        
        $this->logNotification($sponsor['user_id'], $child['child_id'], 'birthday', null, $birthday_date, $status, $error);
        
        return $success;
    }
    
    /**
     * Send Achievement Email
     */
    public function sendAchievementEmail($sponsor, $child, $achievement) {
        if ($this->isNotificationSent($sponsor['user_id'], $child['child_id'], 'achievement', $achievement['upload_id'])) {
            return true;
        }
        
        $variables = [
            'sponsor_name' => $sponsor['first_name'] . ' ' . $sponsor['last_name'],
            'child_name' => $child['first_name'] . ' ' . $child['last_name'],
            'child_first_name' => $child['first_name'],
            'achievement_title' => 'New Achievement',
            'achievement_date' => date('F j, Y', strtotime($achievement['upload_date'])),
            'achievement_description' => isset($achievement['description']) ? $achievement['description'] : 'A new achievement has been added!',
            'ngo_name' => SMTP_FROM_NAME,
            'dashboard_link' => $this->getDashboardLink('sponsor', $child['child_id']),
            'unsubscribe_link' => $this->getUnsubscribeLink($sponsor['user_id'])
        ];
        
        $subject = "🏆 Great News! {$child['first_name']} Achieved Something Amazing!";
        
        $success = $this->sendEmail(
            $sponsor['email'],
            $sponsor['first_name'] . ' ' . $sponsor['last_name'],
            $subject,
            'email_achievement.html',
            $variables
        );
        
        $status = $success ? 'sent' : 'failed';
        $error = $success ? null : 'Email sending failed';
        
        $this->logNotification($sponsor['user_id'], $child['child_id'], 'achievement', $achievement['upload_id'], null, $status, $error);
        
        return $success;
    }
    
    /**
     * Send Report Email
     */
    public function sendReportEmail($sponsor, $child, $report) {
        if ($this->isNotificationSent($sponsor['user_id'], $child['child_id'], 'report', $report['report_id'])) {
            return true;
        }
        
        $variables = [
            'sponsor_name' => $sponsor['first_name'] . ' ' . $sponsor['last_name'],
            'child_name' => $child['first_name'] . ' ' . $child['last_name'],
            'child_first_name' => $child['first_name'],
            'report_title' => 'Progress Report - ' . date('F Y', strtotime($report['report_date'])),
            'report_date' => date('F j, Y', strtotime($report['report_date'])),
            'report_summary' => substr(strip_tags($report['report_text']), 0, 200) . '...',
            'ngo_name' => SMTP_FROM_NAME,
            'dashboard_link' => $this->getDashboardLink('sponsor', $child['child_id']),
            'unsubscribe_link' => $this->getUnsubscribeLink($sponsor['user_id'])
        ];
        
        $subject = "📊 New Progress Report Available for {$child['first_name']}";
        
        $success = $this->sendEmail(
            $sponsor['email'],
            $sponsor['first_name'] . ' ' . $sponsor['last_name'],
            $subject,
            'email_report.html',
            $variables
        );
        
        $status = $success ? 'sent' : 'failed';
        $error = $success ? null : 'Email sending failed';
        
        $this->logNotification($sponsor['user_id'], $child['child_id'], 'report', $report['report_id'], null, $status, $error);
        
        return $success;
    }
    
    /**
     * Send Event Email
     */
    public function sendEventEmail($sponsor, $child, $event) {
        if ($this->isNotificationSent($sponsor['user_id'], $child['child_id'], 'event', $event['event_id'], null)) {
            return true;
        }
        
        $variables = [
            'sponsor_name' => $sponsor['first_name'] . ' ' . $sponsor['last_name'],
            'child_name' => $child['first_name'] . ' ' . $child['last_name'],
            'child_first_name' => $child['first_name'],
            'event_title' => $event['title'],
            'event_date' => date('F j, Y', strtotime($event['event_date'])),
            'event_description' => isset($event['description']) ? $event['description'] : 'An exciting event is coming up!',
            'event_type' => ucfirst($event['event_type']),
            'ngo_name' => SMTP_FROM_NAME,
            'dashboard_link' => $this->getDashboardLink('sponsor', $child['child_id']),
            'unsubscribe_link' => $this->getUnsubscribeLink($sponsor['user_id'])
        ];
        
        $subject = "🎉 Exciting Event Coming Up for {$child['first_name']}!";
        
        $success = $this->sendEmail(
            $sponsor['email'],
            $sponsor['first_name'] . ' ' . $sponsor['last_name'],
            $subject,
            'email_event.html',
            $variables
        );
        
        $status = $success ? 'sent' : 'failed';
        $error = $success ? null : 'Email sending failed';
        
        $this->logNotification($sponsor['user_id'], $child['child_id'], 'event', $event['event_id'], null, $status, $error);
        
        return $success;
    }
    
    /**
     * Send Event Reminder Email
     */
    public function sendEventReminderEmail($sponsor, $child, $event) {
        if ($this->isNotificationSent($sponsor['user_id'], $child['child_id'], 'event', $event['event_id'], $event['event_date'])) {
            return true;
        }
        
        $days_until = $this->getDaysUntil($event['event_date']);
        
        $variables = [
            'sponsor_name' => $sponsor['first_name'] . ' ' . $sponsor['last_name'],
            'child_name' => $child['first_name'] . ' ' . $child['last_name'],
            'child_first_name' => $child['first_name'],
            'event_title' => $event['title'],
            'event_date' => date('F j, Y', strtotime($event['event_date'])),
            'days_until' => $days_until,
            'ngo_name' => SMTP_FROM_NAME,
            'dashboard_link' => $this->getDashboardLink('sponsor', $child['child_id']),
            'unsubscribe_link' => $this->getUnsubscribeLink($sponsor['user_id'])
        ];
        
        $subject = "⏰ Reminder: {$event['title']} in {$days_until} Days!";
        
        $success = $this->sendEmail(
            $sponsor['email'],
            $sponsor['first_name'] . ' ' . $sponsor['last_name'],
            $subject,
            'email_event_reminder.html',
            $variables
        );
        
        $status = $success ? 'sent' : 'failed';
        $error = $success ? null : 'Email sending failed';
        
        $this->logNotification($sponsor['user_id'], $child['child_id'], 'event', $event['event_id'], $event['event_date'], $status, $error);
        
        return $success;
    }
    
    // Helper methods...
    
    private function calculateAge($dob) {
        $birthDate = new DateTime($dob);
        $today = new DateTime();
        return $today->diff($birthDate)->y;
    }
    
    private function getDaysUntil($date) {
        $target = new DateTime($date);
        $today = new DateTime();
        $diff = $today->diff($target);
        return $diff->days;
    }
    
    private function getDashboardLink($role, $child_id) {
        $base_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]";
        
        if ($role === 'sponsor') {
            return $base_url . "/sponser/sponsored_children.php?child_id=" . $child_id;
        } else {
            return $base_url . "/staff/child_edit.php?id=" . $child_id;
        }
    }
    
    private function getUnsubscribeLink($user_id) {
        $base_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]";
        return $base_url . "/unsubscribe.php?user_id=" . base64_encode($user_id);
    }
}
?>