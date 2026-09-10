<?php
/**
 * Lakeland Graphics — mail configuration TEMPLATE.
 * Copy to config.php on the server and fill in real values.
 * config.php is gitignored and must never be committed.
 * Used by send-quote.php (quote form) and send-contact.php (Contact Us form).
 */
return [
    // --- Brevo SMTP relay (Brevo > SMTP & API > SMTP) ---
    'smtp_host'   => 'smtp-relay.brevo.com',
    'smtp_port'   => 587,                          // 587 with 'tls'
    'smtp_secure' => 'tls',
    'smtp_user'   => 'CHANGE_ME',                  // Brevo SMTP login
    'smtp_pass'   => 'CHANGE_ME',                  // Brevo SMTP key

    'from_email'  => 'sales@lakelandgraphics.com', // domain is DKIM-authenticated in Brevo
    'from_name'   => 'Lakeland Graphics Website',
    'to_email'    => 'sales@lakelandgraphics.com', // quote form inbox + auto-reply Reply-To
    'to_name'     => 'Lakeland Graphics Sales',
    'cc_emails'   => [],
    'send_confirmation' => true,

    // --- Contact Us form: department key => recipients ---
    // contact.html posts only the KEY (e.g. "Sales Inquiries"); staff addresses
    // live here and nowhere else. Every <option value="…"> in contact.html needs
    // a key that matches EXACTLY (spelling, spaces, capitals), or that choice is
    // rejected. 'label' is the name used in the emails. 'to' takes one or more
    // addresses. All four departments must sit INSIDE this 'contact_departments'
    // array — one stray "]," closes it early and silently drops the rest.
    'contact_departments' => [
        'Sales Inquiries' => [
            'label' => 'Sales Inquiries',
            'to'    => ['person1@example.com'],
        ],
        'Accounting Inquiries' => [
            'label' => 'Accounting Inquiries',
            'to'    => ['person2@example.com', 'person1@example.com'],
        ],
        'Shipment Inquiries' => [
            'label' => 'Shipment questions',
            'to'    => ['person1@example.com'],
        ],
        'General Inquiries' => [
            'label' => 'General questions',
            'to'    => ['person1@example.com'],
        ],
    ],
];
