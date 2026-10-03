<?php
/**
 * Mail Configuration
 * 
 * Configure your SMTP settings here. For production, use environment variables.
 * For development, you can use MailHog (localhost:1025) or a real SMTP service.
 */

return [
    // Mail driver: 'smtp', 'mail', 'sendmail', 'log'
    'driver' => getenv('ODCI_MAIL_DRIVER') ?: 'smtp',

    // SMTP Configuration (used when driver is 'smtp')
    'smtp' => [
        'host' => getenv('ODCI_SMTP_HOST') ?: 'localhost',
        'port' => (int)(getenv('ODCI_SMTP_PORT') ?: 25),
        'username' => getenv('ODCI_SMTP_USERNAME') ?: '',
        'password' => getenv('ODCI_SMTP_PASSWORD') ?: '',
        'encryption' => getenv('ODCI_SMTP_ENCRYPTION') ?: '', // 'tls', 'ssl', or ''
        'auth' => !empty(getenv('ODCI_SMTP_USERNAME')),
    ],

    // From address
    'from' => [
        'address' => getenv('ODCI_MAIL_FROM_ADDRESS') ?: 'noreply@cvsu.edu.ph',
        'name' => getenv('ODCI_MAIL_FROM_NAME') ?: 'ODCI Document Hub',
    ],

    // Development: log emails to file instead of sending
    'log' => [
        'path' => __DIR__ . '/../storage/logs/mail.log',
    ],
];