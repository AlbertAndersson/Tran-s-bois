<?php
declare(strict_types=1);

// Review candidate only. No credentials, approval evidence or deployment.
$config=require __DIR__.'/production-config.example.php';
$config['public_base_url']='https://socen.se';
$config['seller_legal_name']='Tranås Bois Bandyklubb';
$config['seller_org_number']='826500-4351';
$config['support_email']='bois@alberiq.se';
$config['mail']['from']='bois@alberiq.se';
$config['membership_period']='calendar_year';
$config['matchkit_batch']=['mode'=>'monthly','day'=>null];
$config['consent_validity_days']=180;
$config['retention']['rules']=[
    'sales_events_days'=>90,
    'sales_sessions_days'=>90,
    'consent_inactive_days'=>180,
];
$config['retention']['policy_reference']='P19-ANSWERS-20261112';
// Keep apply disabled, legal hold enabled, mail/checkout/live disabled and
// production_decisions empty. Approved durations alone never permit deletion.
return $config;
