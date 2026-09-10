<?php
/**
 * Lakeland Graphics — Contact Us form handler (Brevo SMTP)
 *
 * Modelled on send-quote.php: same SMTP settings from config.php, honeypot +
 * timing check, server-side validation, branded notification + auto-reply.
 *
 * ROUTING: the form posts a department KEY (e.g. "Sales Inquiries"). The staff
 * addresses for each key live ONLY in the server's config.php under
 * 'contact_departments' (see config.example.php) — never in contact.html or the
 * repo. Keys must match exactly; unknown keys are rejected. To add a department, add it to config.php on the server first,
 * then add a matching <option value="key"> to contact.html.
 *
 * PHP 7.1+ syntax; the server runs it on ea-php82. A blank HTTP 500 with an
 * empty body means PHP fell back to 5.6 — see the handler block at the top of
 * .htaccess and docs/GO-LIVE-RUNBOOK.md.
 */

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

const SEND_FAILED = 'We could not send your message. Please call us at 800.495.8107 or email sales@lakelandgraphics.com.';

function fail(string $msg, int $code = 400): void {
    http_response_code($code);
    echo json_encode(['ok' => false, 'error' => $msg]);
    exit;
}

/** A posted text field, trimmed. Arrays (name[]=…) and missing fields become ''. */
function field(string $key): string {
    $v = $_POST[$key] ?? '';
    return is_string($v) ? trim($v) : '';
}

function h(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
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

/** A PHPMailer wired to the SMTP settings in config.php, From already set. */
function lg_mailer(array $cfg): PHPMailer {
    $m = new PHPMailer(true);
    $m->isSMTP();
    $m->Host       = $cfg['smtp_host'];
    $m->SMTPAuth   = true;
    $m->Username   = $cfg['smtp_user'];
    $m->Password   = $cfg['smtp_pass'];
    $m->SMTPSecure = $cfg['smtp_secure'];   // 'tls' (587) for Brevo
    $m->Port       = (int)$cfg['smtp_port'];
    $m->CharSet    = 'UTF-8';
    $m->setFrom($cfg['from_email'], $cfg['from_name']);
    return $m;
}

/** Wraps table rows (<tr>…</tr>) in the branded email frame used by send-quote.php. */
function lg_email_shell(string $rows): string {
    return <<<HTML
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
{$rows}
<tr><td style="background:#f6f7f8;padding:20px 32px;border-top:1px solid #e6e8ea;">
<p style="font:12px/1.7 Arial,Helvetica,sans-serif;color:#8a9096;margin:0;">Lakeland Graphics &middot; 9444 Deerwood Lane N, Maple Grove, MN 55369<br>Durable custom graphics since 1987 &middot; Woman-owned &middot; 100% Made in the USA</p>
</td></tr>
</table>
</td></tr>
</table>
</body></html>
HTML;
}

/* ---------- anti-spam ---------- */

// Honeypot: real users never fill this in.
if (field('website') !== '') {
    echo json_encode(['ok' => true]); // silently accept, don't send
    exit;
}

// Timing check: bots submit instantly.
$started = (int)field('started');
if ($started > 0 && (time() * 1000 - $started) < 2500) {
    echo json_encode(['ok' => true]);
    exit;
}

/* ---------- validate ---------- */

$name    = field('name');
$company = field('company');
$email   = field('email');
$phone   = field('phone');
$deptKey = field('department');
$comment = field('comment');

if ($name === '')  fail('Please enter your name.');
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) fail('Please enter a valid email address.');
if ($deptKey === '') fail("Please choose who you'd like to reach.");
if ($comment === '') fail('Please enter a message.');

// Byte caps, looser than the form's maxlength (which counts characters).
if (strlen($name) > 800 || strlen($company) > 800 || strlen($phone) > 160) {
    fail('One of your entries is too long.');
}
if (strlen($comment) > 20000) fail('Please keep your message under 5,000 characters.');

// Strip anything header-injection-ish out of values used in headers.
$name    = preg_replace('/[\r\n]+/', ' ', $name);
$company = preg_replace('/[\r\n]+/', ' ', $company);
$email   = preg_replace('/[\r\n]+/', '', $email);

/* ---------- route: department key -> recipients (config.php only) ---------- */

$departments = $cfg['contact_departments'] ?? null;
if (!is_array($departments) || $departments === []) {
    error_log("Lakeland contact form: 'contact_departments' is missing from config.php (see config.example.php).");
    fail(SEND_FAILED, 500);
}

$dept = $departments[$deptKey] ?? null;
if (!is_array($dept)) fail("Please choose who you'd like to reach.");

$deptLabel  = trim((string)($dept['label'] ?? '')) ?: ucfirst($deptKey);
$recipients = [];
foreach ((array)($dept['to'] ?? []) as $addr) {
    if (is_string($addr) && filter_var(trim($addr), FILTER_VALIDATE_EMAIL)) {
        $recipients[] = trim($addr);
    }
}
if ($recipients === []) {
    error_log("Lakeland contact form: department '{$deptKey}' has no valid 'to' addresses in config.php.");
    fail(SEND_FAILED, 500);
}

/* ---------- compose ---------- */

$rows = [
    'Name'       => $name,
    'Company'    => $company !== '' ? $company : '—',
    'Email'      => $email,
    'Phone'      => $phone !== '' ? $phone : '—',
    'Department' => $deptLabel,
];

$rowsHtml = '';
foreach ($rows as $k => $v) {
    $rowsHtml .= '<tr><td style="padding:9px 16px 9px 0;border-bottom:1px solid #e6e8ea;font:700 11px/1.5 Arial,Helvetica,sans-serif;'
               . 'color:#8a9096;letter-spacing:.08em;text-transform:uppercase;white-space:nowrap;vertical-align:top;">' . h($k) . '</td>'
               . '<td style="padding:9px 0;border-bottom:1px solid #e6e8ea;font:15px/1.5 Arial,Helvetica,sans-serif;color:#0f1216;">' . h($v) . '</td></tr>';
}
$nameHtml    = h($name);
$labelHtml   = h($deptLabel);
$commentHtml = nl2br(h($comment)); // not white-space:pre-wrap — Outlook ignores it

$notice = <<<HTML
<tr><td style="padding:30px 32px 8px;">
<div style="font:700 11px/1 Arial,Helvetica,sans-serif;color:#1488B6;letter-spacing:.1em;text-transform:uppercase;margin:0 0 12px;">Contact form &middot; {$labelHtml}</div>
<h1 style="font:700 22px/1.35 Arial,Helvetica,sans-serif;color:#0f1216;margin:0 0 18px;">New message from {$nameHtml}</h1>
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-top:1px solid #e6e8ea;">{$rowsHtml}</table>
</td></tr>
<tr><td style="padding:18px 32px 32px;">
<div style="font:700 11px/1 Arial,Helvetica,sans-serif;color:#8a9096;letter-spacing:.08em;text-transform:uppercase;margin:0 0 10px;">Message</div>
<div style="font:15px/1.7 Arial,Helvetica,sans-serif;color:#3a3f44;background:#f6f7f8;border-radius:10px;padding:16px 18px;">{$commentHtml}</div>
<p style="font:13px/1.6 Arial,Helvetica,sans-serif;color:#8a9096;margin:18px 0 0;">Reply to this email to answer {$nameHtml} directly.</p>
</td></tr>
HTML;
$html = lg_email_shell($notice);

$plain = "New contact form message — {$deptLabel}\n\n";
foreach ($rows as $k => $v) { $plain .= "$k: $v\n"; }
$plain .= "\nMessage:\n{$comment}\n";

/* ---------- send ---------- */

try {
    $mail = lg_mailer($cfg);
    foreach ($recipients as $r) { $mail->addAddress($r); }

    // Replying to the notification emails the customer directly.
    $mail->addReplyTo($email, $name);

    $mail->isHTML(true);
    $mail->Subject = 'Contact form — ' . $deptLabel . ' — ' . $name . ($company !== '' ? ' (' . $company . ')' : '');
    $mail->Body    = $html;
    $mail->AltBody = $plain;
    $mail->send();
} catch (\Throwable $e) {
    error_log('Lakeland contact form SMTP error: ' . $e->getMessage());
    fail(SEND_FAILED, 500);
}

/* ---------- optional customer confirmation ---------- */

if (!empty($cfg['send_confirmation'])) {
    // The auto-reply goes to whatever address was typed in, so anything echoed
    // back is a spam vector. Never echo the message; greet by name only when it
    // looks like a name.
    $greet     = (strlen($name) <= 60 && !preg_match('#://|www\.|\.[a-z]{2,}/#i', $name)) ? $name : '';
    $greetHtml = $greet !== '' ? ', ' . h($greet) : '';

    $thanks = <<<HTML
<tr><td style="padding:34px 32px 8px;">
<h1 style="font:700 22px/1.35 Arial,Helvetica,sans-serif;color:#0f1216;margin:0 0 16px;">Thanks{$greetHtml} &mdash; we&rsquo;ve got your message.</h1>
<p style="font:15px/1.7 Arial,Helvetica,sans-serif;color:#3a3f44;margin:0 0 16px;">It went straight to the right person on our team (<strong style="color:#0f1216;">{$labelHtml}</strong>), and we&rsquo;ll get back to you <strong style="color:#0f1216;">the same or next business day</strong>.</p>
<p style="font:15px/1.7 Arial,Helvetica,sans-serif;color:#3a3f44;margin:0 0 26px;">Thought of something to add, or in a hurry? Just reply to this email or give us a call &mdash; we&rsquo;re glad to help.</p>
</td></tr>
<tr><td style="padding:0 32px 34px;">
<table role="presentation" cellpadding="0" cellspacing="0"><tr>
<td style="padding:0 22px 0 0;font:14px/1.5 Arial,Helvetica,sans-serif;color:#0f1216;">Call&nbsp;<a href="tel:8004958107" style="color:#0f1216;text-decoration:none;font-weight:700;">800.495.8107</a></td>
<td style="font:14px/1.5 Arial,Helvetica,sans-serif;color:#0f1216;">Email&nbsp;<a href="mailto:sales@lakelandgraphics.com" style="color:#1488B6;text-decoration:none;font-weight:700;">sales@lakelandgraphics.com</a></td>
</tr></table>
</td></tr>
HTML;

    try {
        $ack = lg_mailer($cfg);
        $ack->addAddress($email, $greet); // display name shows in their client too
        // Replies go to the general inbox, never to a department's staff —
        // those addresses stay private.
        $ack->addReplyTo($cfg['to_email'] ?? $cfg['from_email'], $cfg['to_name'] ?? $cfg['from_name']);
        $ack->isHTML(true);
        $ack->Subject = 'Thanks — we\'ve got your message | Lakeland Graphics';
        $ack->Body    = lg_email_shell($thanks);
        $ack->AltBody = 'Thanks' . ($greet !== '' ? ', ' . $greet : '') . " — we've got your message.\n\n"
                      . "It went straight to the right person on our team ({$deptLabel}), and we'll get back "
                      . "to you the same or next business day.\n\n"
                      . "Thought of something to add, or in a hurry? Reply to this email or call us.\n\n"
                      . "Call: 800.495.8107\nEmail: sales@lakelandgraphics.com\n\n"
                      . "Lakeland Graphics\n9444 Deerwood Lane N, Maple Grove, MN 55369\n"
                      . "Durable custom graphics since 1987 · 100% Made in the USA";
        $ack->send();
    } catch (\Throwable $e) {
        error_log('Lakeland contact form auto-reply error: ' . $e->getMessage()); // non-fatal
    }
}

echo json_encode(['ok' => true]);
