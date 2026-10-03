<?php
declare(strict_types=1);

// Copy only to a private directory outside public_html. Never add credentials here.
return [
    'mode' => 'production',
    'production_config_version' => 1,
    'production_launch_enabled' => false,
    'checkout_enabled' => false,
    'payment_provider' => 'disabled',
    'payment_webhook_secret' => '',
    'stripe_mode' => 'test',
    'stripe_secret_key' => '',
    'stripe_webhook_secret' => '',
    'stripe_swish_enabled' => false,
    'mail_transport' => 'disabled',
    'payment_mail_transport' => 'disabled',
    'sales_tracking_enabled' => false,
    'external_analytics' => false,
    'admin_token' => '', // Future break-glass secret; not the P14 personal identity model.
    // No real personal accounts are seeded. Production never accepts admin_token.
    'personal_admin' => ['enabled'=>false,'users_file'=>'','state_dir'=>'','public_root'=>''],
    // Provision an existing 0700 directory outside the existing public root.
    'observability' => ['enabled'=>false,'log_dir'=>'','public_root'=>''],
    'allowed_origins' => [],
    'public_base_url' => '', // Final URL requires a business decision.
    'seller_legal_name' => '',
    'seller_org_number' => '',
    'support_email' => '',
    'terms_url' => '',
    'privacy_url' => '',
    'merchant_verified' => false,
    'stripe_fees_approved' => false,
    'refund_policy_approved' => false,
    'membership_validity_days' => 365, // Staging proposal; launch requires an explicit decision.
    'production_decisions' => [],
    // Both identity and credentials must differ. Database name equality is denied
    // even when DNS aliases could point at the same server.
    'staging_db_identity' => ['host' => '', 'database' => '', 'user' => ''],
    'db' => ['host' => '', 'port' => 3306, 'database' => '', 'user' => '', 'password' => ''],
];
