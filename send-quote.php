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
        $nameHtml = htmlspecialchars($name);
        $ack->Subject = 'Thanks — we\'ve got your request | Lakeland Graphics';
        $ack->Body = <<<HTML
<!DOCTYPE html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head>
<body style="margin:0;padding:0;background:#eef0f2;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#eef0f2;">
<tr><td align="center" style="padding:28px 12px;">
<table role="presentation" width="600" cellpadding="0" cellspacing="0" style="width:600px;max-width:600px;background:#ffffff;border-radius:14px;overflow:hidden;">
<tr><td style="background:#0f1216;padding:24px 32px;">
<div style="font:700 20px/1 Arial,Helvetica,sans-serif;color:#ffffff;letter-spacing:.08em;">LAKELAND GRAPHICS</div>
<div style="height:3px;width:46px;background:#EC1187;margin-top:12px;border-radius:2px;line-height:3px;font-size:0;">&nbsp;</div>
</td></tr>
<tr><td style="padding:34px 32px 8px;">
<h1 style="font:700 22px/1.35 Arial,Helvetica,sans-serif;color:#0f1216;margin:0 0 16px;">Thanks, {$nameHtml} &mdash; we&rsquo;ve got your request.</h1>
<p style="font:15px/1.7 Arial,Helvetica,sans-serif;color:#3a3f44;margin:0 0 16px;">A real person on our team is reviewing it now. We&rsquo;ll get back to you <strong style="color:#0f1216;">within 1&ndash;2 business days</strong> with a quote and a recommendation for the right build.</p>
<p style="font:15px/1.7 Arial,Helvetica,sans-serif;color:#3a3f44;margin:0 0 26px;">Thought of something to add, or in a hurry? Just reply to this email or give us a call &mdash; we&rsquo;re glad to help.</p>
</td></tr>
<tr><td style="padding:0 32px 34px;">
<table role="presentation" cellpadding="0" cellspacing="0"><tr>
<td style="padding:0 22px 0 0;font:14px/1.5 Arial,Helvetica,sans-serif;color:#0f1216;">Call&nbsp;<a href="tel:8004958107" style="color:#0f1216;text-decoration:none;font-weight:700;">800.495.8107</a></td>
<td style="font:14px/1.5 Arial,Helvetica,sans-serif;color:#0f1216;">Email&nbsp;<a href="mailto:sales@lakelandgraphics.com" style="color:#1488B6;text-decoration:none;font-weight:700;">sales@lakelandgraphics.com</a></td>
</tr></table>
</td></tr>
<tr><td style="background:#f6f7f8;padding:20px 32px;border-top:1px solid #e6e8ea;">
<p style="font:12px/1.7 Arial,Helvetica,sans-serif;color:#8a9096;margin:0;">Lakeland Graphics &middot; 9444 Deerwood Lane N, Maple Grove, MN 55369<br>Durable custom graphics since 1987 &middot; Woman-owned &middot; 100% Made in the USA</p>
</td></tr>
</table>
</td></tr>
</table>
</body></html>
HTML;
        $ack->AltBody = "Thanks, {$name} — we've got your request.\n\n"
                      . "A real person on our team is reviewing it now. We'll get back to you within "
                      . "1-2 business days with a quote and a recommendation for the right build.\n\n"
                      . "Thought of something to add, or in a hurry? Reply to this email or call us.\n\n"
                      . "Call: 800.495.8107\nEmail: sales@lakelandgraphics.com\n\n"
                      . "Lakeland Graphics\n9444 Deerwood Lane N, Maple Grove, MN 55369\n"
                      . "Durable custom graphics since 1987 · 100% Made in the USA";
        try { $ack->send(); } catch (MailException $e) { /* non-fatal */ }
    }

    echo json_encode(['ok' => true]);
} catch (MailException $e) {
    error_log('Lakeland quote form SMTP error: ' . $mail->ErrorInfo);
    fail('We could not send your request. Please call us at 800.495.8107 or email sales@lakelandgraphics.com.', 500);
}
