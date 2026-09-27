<?php
declare(strict_types=1);

return [
    'mode' => 'staging',
    'admin_token' => 'replace-with-long-random-token',
    'allowed_origins' => ['https://example.test'],
    'mail_transport' => 'disabled',
    'payment_provider' => 'mock',
    'payment_webhook_secret' => 'replace-with-at-least-32-random-characters',
    'payment_mail_transport' => 'disabled',
    'membership_validity_days' => 365,
    'db' => [
        'host' => '127.0.0.1',
        'port' => 3306,
        'database' => 'database_name',
        'user' => 'database_user',
        'password' => 'database_password',
    ],
];
