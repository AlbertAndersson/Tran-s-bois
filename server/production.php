<?php
declare(strict_types=1);
require_once __DIR__ . '/security.php';

function bois_production_config_checks(array $config, bool $closed = true): array
{
    $db=$config['db']??[];
    $staging=$config['staging_db_identity']??[];
    $nonempty=static fn($v): bool => is_string($v)&&trim($v)!=='';
    $checks=[
        'production_mode'=>($config['mode']??null)==='production',
        'production_config_version'=>($config['production_config_version']??null)===1,
        'db_credentials'=>is_array($db)&&array_reduce(['host','database','user','password'],fn($ok,$k)=>$ok&&$nonempty($db[$k]??null),true),
        'db_port'=>is_int($db['port']??null)&&$db['port']>0&&$db['port']<=65535,
        'staging_identity'=>is_array($staging)&&array_reduce(['host','database','user'],fn($ok,$k)=>$ok&&$nonempty($staging[$k]??null),true),
        'distinct_database'=>$nonempty($db['database']??null)&&$nonempty($staging['database']??null)&&strcasecmp($db['database'],$staging['database'])!==0,
        'distinct_db_user'=>$nonempty($db['user']??null)&&$nonempty($staging['user']??null)&&strcasecmp($db['user'],$staging['user'])!==0,
        'private_admin_secret'=>is_string($config['admin_token']??null)&&strlen($config['admin_token'])>=32,
    ];
    if($closed){
        foreach(['production_launch_enabled','checkout_enabled','sales_tracking_enabled','external_analytics','stripe_swish_enabled'] as $key){
            $checks[$key.'_closed']=array_key_exists($key,$config)&&$config[$key]===false;
        }
        foreach(['mail_transport','payment_mail_transport'] as $key) $checks[$key.'_closed']=($config[$key]??null)==='disabled';
        $checks['payment_closed']=($config['payment_provider']??null)==='disabled';
        $checks['stripe_mode_test']=($config['stripe_mode']??null)==='test';
        $checks['stripe_credentials_absent']=($config['stripe_secret_key']??null)===''&&($config['stripe_webhook_secret']??null)==='';
    }
    return $checks;
}

function bois_production_require_checks(array $checks): void
{
    $missing=array_keys(array_filter($checks,static fn($ok)=>$ok!==true));
    if($missing) throw new RuntimeException('Blocked checks: '.implode(',',$missing));
}

function bois_production_decision_keys(): array
{
    return ['go_live','p18_release','backup_restore','personal_admin_mfa','seller_merchant_bank',
        'legal_policies','product_partner_prices','membership_period','support_mail','domain_dns_tls',
        'privacy_retention','final_smoke_rollback'];
}

function bois_production_decision_checks(array $config): array
{
    $checks=[];
    foreach(bois_production_decision_keys() as $decision){
        $value=$config['production_decisions'][$decision]??null;
        $checks['decision_'.$decision]=is_array($value)&&($value['approved']??null)===true
            &&is_string($value['reference']??null)&&trim($value['reference'])!=='';
    }
    return $checks;
}

// A closed production runtime denies all commerce traffic before a DB connection.
// Signed notifications for earlier payments remain available through the existing
// payment_webhook route when a future approved runtime closes new sales.
function bois_production_http_gate(array $config, string $action): void
{
    if(($config['mode']??'')!=='production') return;
    if($action==='payment_webhook') return;
    if(($config['production_launch_enabled']??false)!==true){
        bois_security_reject(503,'Butiken är inte öppen.');
    }
    bois_production_require_checks(bois_production_config_checks($config,false));
    if(($config['checkout_enabled']??false)!==true||!function_exists('bois_p8_readiness')
        ||bois_p8_readiness($config)['ready_for_production_launch']!==true
        ||in_array(false,bois_production_decision_checks($config),true)){
        bois_security_reject(503,'Butiken är inte öppen.');
    }
}
