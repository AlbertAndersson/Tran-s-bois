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
    // P17: private config only; no delivery enabled by provisioning these values.
    'mail' => [
        'from'=>'', 'sink_dir'=>'', 'public_root'=>'',
        'external_delivery_approved'=>false, 'domain_verified'=>false,
        'provider_verified'=>false,
    ],
    'sales_tracking_enabled' => false,
    'external_analytics' => false,
    'admin_token' => '', // Future break-glass secret; not the P14 personal identity model.
    // No real personal accounts are seeded. Production never accepts admin_token.
    'personal_admin' => ['enabled'=>false,'users_file'=>'','state_dir'=>'','public_root'=>''],
    // Provision an existing 0700 directory outside the existing public root.
    'observability' => ['enabled'=>false,'log_dir'=>'','public_root'=>''],
    // TBD: business-approved durations; no production fallback to staging's 180 days.
    'consent_validity_days' => null,
    'consent_cookie_path' => null,
    'retention' => [
        'rules'=>['sales_events_days'=>null,'sales_sessions_days'=>null,'consent_inactive_days'=>null],
        'batch_size'=>100,'apply_enabled'=>false,'legal_hold'=>true,
        'policy_reference'=>'','backup_reference'=>'','approved_target'=>[],
    ],
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
