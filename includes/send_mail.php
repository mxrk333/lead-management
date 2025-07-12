<?php
// includes/send_email.php
require_once dirname(__DIR__) . '/vendor/autoload.php'; // Adjust path to PHPMailer autoload.php
require_once dirname(__DIR__) . '/config/mailer.php'; // Your mailer configuration

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

function sendEmail($toEmail, $toName, $subject, $body, $altBody = '') {
    $mail = new PHPMailer(true); // Pass `true` to enable exceptions

    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host = SMTP_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = mark.innersparc@gmail.com;
        $mail->Password = tqyclbqbvqwyfcaa;
        $mail->SMTPSecure = SMTP_ENCRYPTION;
        $mail->Port = SMTP_PORT;
        $mail->CharSet = 'UTF-8';

        // Recipients
        $mail->setFrom(MAIL_FROM_EMAIL, MAIL_FROM_NAME);
        $mail->addAddress($toEmail, $toName);

        // Content
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body = $body;
        $mail->AltBody = $altBody ?: strip_tags($body); // Plain text for non-HTML mail clients

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Message could not be sent. Mailer Error: {$mail->ErrorInfo}");
        return false;
    }
}
