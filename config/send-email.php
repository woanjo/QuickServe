<?php
// config/email_config.php

require_once __DIR__ . '/../vendor/autoload.php'; // Load PHPMailer library

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

class EmailConfig {
    
    private static $instance = null;
    private $mail;

    private function __construct() {
        try {
            $this->mail = new PHPMailer(true);
            
            // Set up gmail connection
            $this->mail->isSMTP();
            $this->mail->Host = "smtp.gmail.com";
            $this->mail->SMTPAuth = true;
            $this->mail->Username = "quickserveadmin@gmail.com";
            $this->mail->Password = "sgtn tlua ffqm eddu"; // app pass
            $this->mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $this->mail->Port = 587;
            
            // Sender info
            $this->mail->setFrom('quickserveadmin@gmail.com', 'QuickServe Volunteer Admin');
        } catch (Exception $e) {
            error_log("EmailConfig constructor error: " . $e->getMessage());
        }
    }

    // Get the single instance
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    // Send email based on status
    public function sendEmail($toEmail, $toName, $missionTitle, $status, $missionDate = '', $hours = '') {
        try {
            $this->mail->clearAddresses();
            $this->mail->clearCCs();
            $this->mail->clearBCCs();
            $this->mail->addAddress($toEmail, $toName);
            $this->mail->isHTML(false); 
            $this->mail->CharSet = 'UTF-8';
            
            if ($status === 'approved') {
                $this->mail->Subject = 'Volunteer Application Approved - ' . $missionTitle;
                $this->mail->Body = $this->getApprovalText($toName, $missionTitle, $missionDate, $hours);
            } elseif ($status === 'rejected') {
                $this->mail->Subject = 'Volunteer Application Update - ' . $missionTitle;
                $this->mail->Body = $this->getRejectionText($toName, $missionTitle);
            } elseif ($status === 'completed') {
                $this->mail->Subject = 'Volunteer Hours Confirmed - ' . $missionTitle;
                $this->mail->Body = $this->getCompletionText($toName, $missionTitle, $missionDate, $hours);
            }
            
            return $this->mail->send(); // Returns true if sent, false if failed
        } catch (Exception $e) {
            error_log("Email failed: " . $this->mail->ErrorInfo);
            return false;
        }
    }
    
    // Approved
    private function getApprovalText($name, $missionTitle, $missionDate, $hours) {
        return "Dear <b>$name</b>,\n\n" .
               "Congratulations! Your volunteer application for <b>'$missionTitle'</b> has been APPROVED.\n\n" .
               "Mission Details:\n" .
               "-Mission: $missionTitle\n" .
               "-Date: $missionDate\n" .
               "-Hours: $hours hours\n\n" .
               "Please arrive on time and complete your assigned hours.\n\n" .
               "Thank you for your dedication to community service!\n\n" .
               "Best regards,\n" .
               "QuickServe Volunteer Team\n" .
               "This is an automated message, please do not reply.";
    }
    
    // Rejected
    private function getRejectionText($name, $missionTitle) {
        return "Dear <b>$name</b> ,\n\n" .
               "Thank you for your interest in volunteering for '$missionTitle'.\n\n" .
               "After careful consideration, we regret to inform you that your application has been REJECTED.\n\n" .
               "Don't be discouraged! There are many other missions available. Please check our other opportunities.\n\n" .
               "Best regards,\n" .
               "QuickServe Volunteer Team\n" .
               "This is an automated message, please do not reply.";
    }
    
    // Confirmed hours
    private function getCompletionText($name, $missionTitle, $missionDate, $hours) {
        return "Dear $name,\n\n" .
               "Great news! Your volunteer hours for '$missionTitle' have been CONFIRMED.\n\n" .
               "Service Record:\n" .
               "- Mission: $missionTitle\n" .
               "- Date: $missionDate\n" .
               "- Hours Completed: $hours hours\n\n" .
               "Thank you for your valuable contribution to the community!\n\n" .
               "Keep up the great work!\n\n" .
               "Best regards,\n" .
               "QuickServe Volunteer Team\n" .
               "This serves as confirmation of your completed volunteer hours.";
    }
}
?>