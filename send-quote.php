<?php
/**
 * Lakeland Graphics — quote form handler (SiteGround SMTP)
 *
 * SETUP (see SITEGROUND-SETUP.md for the full walkthrough):
 *   1. Upload the PHPMailer library to /vendor/PHPMailer/ (see setup doc)
 *   2. Fill in the SMTP settings in config.php
 *   3. Make sure config.php sits OUTSIDE the public web root if possible,
 *      or is protected by the .htaccess rule in the setup doc.
 */

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

function fail(string $msg, int $code = 400): void {
    http_response_code($code);
    echo json_encode(['ok' => false, 'error' => $msg]);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    fail('Method not allowed.', 405);
}

$configPath = __DIR__ . '/config.php';
if (!is_readable($configPath)) {
    fail('Mail is not configured yet.', 500);
}
$cfg = require $configPath;

require __DIR__ . '/vendor/PHPMailer/PHPMailer.php';
require __DIR__ . '/vendor/PHPMailer/SMTP.php';
require __DIR__ . '/vendor/PHPMailer/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as MailException;

/* ---------- anti-spam ---------- */

// Honeypot: real users never fill this in.
if (trim((string)($_POST['website'] ?? '')) !== '') {
    echo json_encode(['ok' => true]); // silently accept, don't send
    exit;
}

// Timing check: bots submit instantly.
$started = (int)($_POST['started'] ?? 0);
if ($started > 0 && (time() * 1000 - $started) < 2500) {
    echo json_encode(['ok' => true]);
    exit;
}

/* ---------- validate ---------- */

$name    = trim((string)($_POST['name'] ?? ''));
$company = trim((string)($_POST['company'] ?? ''));
$email   = trim((string)($_POST['email'] ?? ''));
$phone   = trim((string)($_POST['phone'] ?? ''));
$print   = trim((string)($_POST['print'] ?? ''));
$useCase = trim((string)($_POST['use'] ?? ''));

if ($name === '')  fail('Please enter your name.');
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) fail('Please enter a valid email address.');
if ($print === '') fail('Please choose what you are looking to print.');

// Strip anything header-injection-ish out of values used in headers.
$name  = preg_replace('/[\r\n]+/', ' ', $name);
$email = preg_replace('/[\r\n]+/', '', $email);

/* ---------- collect attachments ---------- */

$maxPerFile  = 12 * 1024 * 1024;   // 12 MB
$maxTotal    = 25 * 1024 * 1024;   // 25 MB — keep under SiteGround's message cap
$allowedExt  = ['jpg','jpeg','png','gif','webp','heic','pdf','ai','eps','svg'];
$attachments = [];
$totalBytes  = 0;
$skipped     = [];

foreach (['photo', 'art'] as $field) {
    if (empty($_FILES[$field]) || !is_array($_FILES[$field]['name'])) continue;

    $count = count($_FILES[$field]['name']);
    for ($i = 0; $i < $count; $i++) {
        if ((int)$_FILES[$field]['error'][$i] !== UPLOAD_ERR_OK) continue;

        $orig = (string)$_FILES[$field]['name'][$i];
        $tmp  = (string)$_FILES[$field]['tmp_name'][$i];
        $size = (int)$_FILES[$field]['size'][$i];
        $ext  = strtolower(pathinfo($orig, PATHINFO_EXTENSION));

        if (!in_array($ext, $allowedExt, true)) { $skipped[] = $orig . ' (file type)'; continue; }
        if ($size > $maxPerFile)                { $skipped[] = $orig . ' (too large)';  continue; }
        if ($totalBytes + $size > $maxTotal)    { $skipped[] = $orig . ' (over total size limit)'; continue; }
        if (!is_uploaded_file($tmp))            { continue; }

        // Sanitize the display filename.
        $safe = preg_replace('/[^A-Za-z0-9._-]/', '_', $orig);
        $attachments[] = ['path' => $tmp, 'name' => $safe];
        $totalBytes += $size;
    }
}

/* ---------- compose ---------- */

$rows = [
    'Name'        => $name,
    'Company'     => $company !== '' ? $company : '—',
    'Email'       => $email,
    'Phone'       => $phone !== '' ? $phone : '—',
    'Looking for' => $print,
];

$html  = '<h2 style="font:600 18px/1.3 Arial,sans-serif;margin:0 0 16px">New quote request</h2>';
$html .= '<table cellpadding="6" style="border-collapse:collapse;font:14px/1.5 Arial,sans-serif">';
foreach ($rows as $k => $v) {
    $html .= '<tr><td style="background:#f4f5f6;font-weight:700">' . htmlspecialchars($k)
           . '</td><td>' . htmlspecialchars($v) . '</td></tr>';
}
$html .= '</table>';

if ($useCase !== '') {
    $html .= '<h3 style="font:600 15px/1.3 Arial,sans-serif;margin:22px 0 6px">Application / use case</h3>'
           . '<p style="font:14px/1.6 Arial,sans-serif;white-space:pre-wrap">'
           . htmlspecialchars($useCase) . '</p>';
}
$html .= '<p style="font:12px/1.5 Arial,sans-serif;color:#666;margin-top:24px">'
       . count($attachments) . ' file(s) attached.';
if ($skipped) {
    $html .= '<br>Not attached: ' . htmlspecialchars(implode(', ', $skipped))
           . ' — ask the customer to send these another way.';
}
$html .= '</p>';

$plain = "New quote request\n\n";
foreach ($rows as $k => $v) { $plain .= "$k: $v\n"; }
if ($useCase !== '') { $plain .= "\nApplication / use case:\n$useCase\n"; }
$plain .= "\n" . count($attachments) . " file(s) attached.\n";
if ($skipped) { $plain .= "Not attached: " . implode(', ', $skipped) . "\n"; }

/* ---------- send ---------- */

$mail = new PHPMailer(true);
try {
    $mail->isSMTP();
    $mail->Host       = $cfg['smtp_host'];
    $mail->SMTPAuth   = true;
    $mail->Username   = $cfg['smtp_user'];
    $mail->Password   = $cfg['smtp_pass'];
    $mail->SMTPSecure = $cfg['smtp_secure'];   // 'ssl' (465) or 'tls' (587)
    $mail->Port       = (int)$cfg['smtp_port'];
    $mail->CharSet    = 'UTF-8';

    // From MUST be a real mailbox on your SiteGround domain, or SMTP will reject it.
    $mail->setFrom($cfg['from_email'], $cfg['from_name']);
    $mail->addAddress($cfg['to_email'], $cfg['to_name']);
    foreach (($cfg['cc_emails'] ?? []) as $cc) { $mail->addCC($cc); }

    // Replying to the notification emails the customer directly.
    $mail->addReplyTo($email, $name);

    foreach ($attachments as $a) { $mail->addAttachment($a['path'], $a['name']); }

    $mail->isHTML(true);
    $mail->Subject = 'Quote request — ' . $name . ($company !== '' ? ' (' . $company . ')' : '');
    $mail->Body    = $html;
    $mail->AltBody = $plain;
    $mail->send();

    /* ---------- optional customer confirmation ---------- */
    if (!empty($cfg['send_confirmation'])) {
        $ack = new PHPMailer(true);
        $ack->isSMTP();
        $ack->Host       = $cfg['smtp_host'];
        $ack->SMTPAuth   = true;
        $ack->Username   = $cfg['smtp_user'];
        $ack->Password   = $cfg['smtp_pass'];
        $ack->SMTPSecure = $cfg['smtp_secure'];
        $ack->Port       = (int)$cfg['smtp_port'];
        $ack->CharSet    = 'UTF-8';
        $ack->setFrom($cfg['from_email'], $cfg['from_name']);
        $ack->addAddress($email, $name);
        $ack->addReplyTo($cfg['to_email'], $cfg['to_name']);
        $ack->isHTML(true);
        $ack->Subject = 'We got your request — Lakeland Graphics';
        $ack->Body    = '<p style="font:15px/1.6 Arial,sans-serif">Thanks for reaching out, '
                      . htmlspecialchars($name) . '.</p>'
                      . '<p style="font:15px/1.6 Arial,sans-serif">We have your request and we\'ll be in touch '
                      . 'within 1&ndash;2 business days with a quote and a recommendation. '
                      . 'If you\'re in a hurry, call us at 800.495.8107.</p>'
                      . '<p style="font:15px/1.6 Arial,sans-serif">&mdash; Lakeland Graphics</p>';
        $ack->AltBody = "Thanks for reaching out, $name.\n\nWe have your request and we'll be in touch "
                      . "within 1-2 business days.\n\n- Lakeland Graphics";
        try { $ack->send(); } catch (MailException $e) { /* non-fatal */ }
    }

    echo json_encode(['ok' => true]);
} catch (MailException $e) {
    error_log('Lakeland quote form SMTP error: ' . $mail->ErrorInfo);
    fail('We could not send your request. Please call us at 800.495.8107 or email sales@lakelandgraphics.com.', 500);
}
