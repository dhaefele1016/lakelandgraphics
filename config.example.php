<?php
/**
 * Lakeland Graphics — mail configuration TEMPLATE.
 * Copy to config.php on the server and fill in real values.
 * config.php is gitignored and must never be committed.
 */
return [
    // --- InMotion SMTP (cPanel > Email Accounts > Connect Devices) ---
    'smtp_host'   => 'mail.lakelandgraphics.com', // confirm exact host in cPanel
    'smtp_port'   => 465,                          // 465 with 'ssl', or 587 with 'tls'
    'smtp_secure' => 'ssl',
    'smtp_user'   => 'website@lakelandgraphics.com',
    'smtp_pass'   => 'CHANGE_ME',

    'from_email'  => 'website@lakelandgraphics.com', // must be a real mailbox on the domain
    'from_name'   => 'Lakeland Graphics Website',
    'to_email'    => 'sales@lakelandgraphics.com',
    'to_name'     => 'Lakeland Graphics Sales',
    'cc_emails'   => [],
    'send_confirmation' => true,
];
