<?php
/**
 * TEMPORARY Brevo SMTP diagnostic. Visit with ?run=1 to run the test.
 * Prints only SERVER responses (never the client-side credential lines).
 * REMOVE this file after diagnosing — it will be deleted on the next deploy.
 */
if (($_GET['run'] ?? '') !== '1') {
    header('Content-Type: text/plain; charset=utf-8');
    echo "Add ?run=1 to the URL to run the SMTP test.";
    exit;
}

require __DIR__ . '/vendor/PHPMailer/PHPMailer.php';
require __DIR__ . '/vendor/PHPMailer/SMTP.php';
require __DIR__ . '/vendor/PHPMailer/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as MailException;

header('Content-Type: text/plain; charset=utf-8');
$cfg = require __DIR__ . '/config.php';

echo "Host: {$cfg['smtp_host']}  Port: {$cfg['smtp_port']}  Secure: {$cfg['smtp_secure']}\n";
echo "Login: {$cfg['smtp_user']}\n";
echo "From: {$cfg['from_email']}   To: {$cfg['to_email']}\n";
echo str_repeat('-', 50) . "\n";

$mail = new PHPMailer(true);
$mail->SMTPDebug   = 2;
$mail->Debugoutput = function ($str, $level) {
    // Never print client-side lines — they contain the encoded credentials.
    if (strpos($str, 'CLIENT -> SERVER') !== false) return;
    echo rtrim($str) . "\n";
};
try {
    $mail->isSMTP();
    $mail->Host       = $cfg['smtp_host'];
    $mail->SMTPAuth   = true;
    $mail->Username   = $cfg['smtp_user'];
    $mail->Password   = $cfg['smtp_pass'];
    $mail->SMTPSecure = $cfg['smtp_secure'];
    $mail->Port       = (int)$cfg['smtp_port'];
    $mail->CharSet    = 'UTF-8';
    $mail->setFrom($cfg['from_email'], $cfg['from_name']);
    $mail->addAddress($cfg['to_email'], $cfg['to_name']);
    $mail->Subject = 'Brevo SMTP test';
    $mail->Body    = 'Test message from mailtest.php';
    $mail->send();
    echo "\n==== RESULT: SUCCESS — message accepted for delivery ====\n";
} catch (MailException $e) {
    echo "\n==== RESULT: FAILED ====\n";
    echo "ErrorInfo: " . $mail->ErrorInfo . "\n";
}
