<?php
declare(strict_types=1);

return [
    'mode' => 'staging',
    'admin_token' => 'replace-with-long-random-token',
    'allowed_origins' => ['https://example.test'],
    'mail_transport' => 'disabled',
    // Staging remains mock. Set to stripe only in a separately approved runtime.
    'payment_provider' => 'mock',
    'payment_webhook_secret' => 'replace-with-at-least-32-random-characters',
    'payment_mail_transport' => 'disabled',

    // P8 Stripe settings. Empty placeholders keep Stripe fail-closed and cost-free.
    'stripe_mode' => 'test',
    'stripe_secret_key' => '',
    'stripe_webhook_secret' => '',
    'stripe_swish_enabled' => false,
    'public_base_url' => 'https://example.test/shop',

    // Production gates. Do not set true until the corresponding decision is verified.
    'seller_legal_name' => '',
    'seller_org_number' => '',
    'support_email' => '',
    'terms_url' => '',
    'privacy_url' => '',
    'merchant_verified' => false,
    'stripe_fees_approved' => false,
    'refund_policy_approved' => false,
    'production_launch_enabled' => false,
    'membership_validity_days' => 365,
    'db' => [
        'host' => '127.0.0.1',
        'port' => 3306,
        'database' => 'database_name',
        'user' => 'database_user',
        'password' => 'database_password',
    ],
];
