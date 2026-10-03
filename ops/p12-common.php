<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/server/production.php';
require_once dirname(__DIR__).'/server/p3_db.php';
require_once dirname(__DIR__).'/server/p5_batch.php';
require_once dirname(__DIR__).'/server/p4_membership.php';
require_once dirname(__DIR__).'/server/p6_payment.php';
require_once dirname(__DIR__).'/server/p7_assortment.php';
require_once dirname(__DIR__).'/server/p9_sales.php';
require_once dirname(__DIR__).'/server/consent.php';

function bois_p12_load(string $path): array
{
    if(PHP_SAPI!=='cli'||$path===''||!is_file($path)) throw new RuntimeException('Private CLI config required.');
    $real=realpath($path);
    $public=getenv('BOIS_PUBLIC_ROOT');
    if(is_string($public)&&$public!==''&&str_starts_with($real,realpath($public).DIRECTORY_SEPARATOR)) throw new RuntimeException('Config must be outside webroot.');
    if((fileperms($path)&0077)!==0) throw new RuntimeException('Config permissions must be private.');
    $config=require $real;
    if(!is_array($config)) throw new RuntimeException('Invalid config.');
    bois_production_require_checks(bois_production_config_checks($config));
    return $config;
}

function bois_p12_tables(): array
{
    $tables=['bois_schema_migrations','bois_suppliers','bois_products','bois_variants','bois_fulfillment_rules',
        'bois_customers','bois_orders','bois_order_items','bois_memberships','bois_payments','bois_supplier_batches',
        'bois_batch_items','bois_email_outbox','bois_events','bois_members','bois_benefit_entitlements',
        'bois_payment_events','bois_payment_outbox','bois_p7_assortment','bois_p7_variants',
        'bois_sales_sessions','bois_sales_events','bois_sales_order_links','bois_consent_choices'];
    sort($tables); return $tables;
}

function bois_p12_versions(): array
{
    return ['20260927_p3_commerce_core_v1','20260927_p4_membership_nordic_v1','20260927_p5_matchkit_batching_v1',
        '20260927_p6_payment_v1','20260927_p7_assortment_v1','20260928_p8_stripe_v1',
        '20260928_p9_sales_engine_v1','20261003_p12_production_bootstrap_v1'];
}

function bois_p12_verify_target(PDO $pdo,array $config): void
{
    $actual=(string)$pdo->query('SELECT DATABASE()')->fetchColumn();
    if($actual!==$config['db']['database']||strcasecmp($actual,$config['staging_db_identity']['database'])===0) throw new RuntimeException('Database isolation failed.');
}

function bois_p12_schema_checks(PDO $pdo): array
{
    $tables=$pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);sort($tables);
    $checks=['exact_table_manifest'=>$tables===bois_p12_tables()];
    if(!$checks['exact_table_manifest']) return $checks;
    $versions=$pdo->query('SELECT version FROM bois_schema_migrations')->fetchAll(PDO::FETCH_COLUMN);sort($versions);
    $expected=bois_p12_versions();sort($expected);
    $checks['migration_ledger']=$versions===$expected;
    // Check altered columns as well as table/ledger presence.
    $columns=['bois_supplier_batches'=>['trigger_reason','csv_sha256','config_json'],
        'bois_payments'=>['checkout_session_ref','checkout_token_hash','method','paid_ore','refunded_ore','effects_status','last_event_id','paid_at','failed_at','cancelled_at','refunded_at','provider_payment_ref','checkout_url']];
    foreach($columns as $table=>$names){
        $actual=$pdo->query('SHOW COLUMNS FROM `'.$table.'`')->fetchAll(PDO::FETCH_COLUMN);
        $checks[$table.'_columns']=count(array_diff($names,$actual))===0;
    }
    return $checks;
}

function bois_p12_readiness(PDO $pdo,array $config): array
{
    bois_p12_verify_target($pdo,$config);
    $checks=bois_production_config_checks($config)+bois_p12_schema_checks($pdo);
    if(($checks['exact_table_manifest']??false)===true){
        // All business data must be empty in the closed candidate. No staging import.
        $catalog=['bois_schema_migrations','bois_suppliers','bois_products','bois_variants','bois_fulfillment_rules','bois_p7_assortment','bois_p7_variants'];
        foreach(array_diff(bois_p12_tables(),$catalog) as $table){
            $checks[$table.'_empty']=(int)$pdo->query('SELECT COUNT(*) FROM `'.$table.'`')->fetchColumn()===0;
        }
        $checks['catalog_blocked']=(int)$pdo->query('SELECT COUNT(*) FROM bois_products WHERE is_public<>0 OR is_orderable<>0')->fetchColumn()===0;
        $checks['p7_unapproved']=(int)$pdo->query('SELECT COUNT(*) FROM bois_p7_assortment WHERE approved<>0')->fetchColumn()===0;
    }
    return ['ready_for_closed_verification'=>!in_array(false,$checks,true),'ready_for_launch'=>false,'checks'=>$checks];
}

function bois_p12_bootstrap(PDO $pdo,array $config): void
{
    bois_production_require_checks(bois_production_config_checks($config));
    bois_p12_verify_target($pdo,$config);
    if((int)$pdo->query("SELECT GET_LOCK('bois_p12_bootstrap',10)")->fetchColumn()!==1) throw new RuntimeException('Bootstrap lock unavailable.');
    try{
        $tables=$pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
        if(array_diff($tables,bois_p12_tables())) throw new RuntimeException('Unexpected tables; target refused.');
        $catalog=['bois_schema_migrations','bois_suppliers','bois_products','bois_variants','bois_fulfillment_rules','bois_p7_assortment','bois_p7_variants'];
        foreach(array_diff(bois_p12_tables(),$catalog) as $table){
            if(in_array($table,$tables,true)&&(int)$pdo->query('SELECT COUNT(*) FROM `'.$table.'`')->fetchColumn()!==0) throw new RuntimeException('Candidate contains business data; refused.');
        }
        if(in_array('bois_schema_migrations',$tables,true)){
            $versions=$pdo->query('SELECT version FROM bois_schema_migrations')->fetchAll(PDO::FETCH_COLUMN);
            if(array_diff($versions,bois_p12_versions())) throw new RuntimeException('Unknown migration; refused.');
            $done=$pdo->prepare('SELECT COUNT(*) FROM bois_schema_migrations WHERE version=?');
            $done->execute(['20261003_p12_production_bootstrap_v1']);
            if((int)$done->fetchColumn()===1){
                bois_production_require_checks(bois_p12_readiness($pdo,$config)['checks']);return;
            }
        }
        bois_p3_apply_schema($pdo);
        // Only repository catalog proposals, never customers/orders or staging dumps.
        bois_p3_seed_catalog($pdo);
        bois_p5_apply_schema($pdo);bois_p4_apply_schema($pdo);bois_p6_apply_schema($pdo);
        bois_p7_apply_schema($pdo);bois_p7_seed_assortment($pdo);
        bois_p8_apply_schema($pdo);bois_p9_apply_schema($pdo);bois_consent_schema($pdo);
        // Test catalog prices are not approved production products.
        $pdo->exec('UPDATE bois_products SET is_public=0,is_orderable=0');
        $pdo->prepare('INSERT IGNORE INTO bois_schema_migrations(version) VALUES(?)')->execute(['20261003_p12_production_bootstrap_v1']);
        bois_production_require_checks(bois_p12_readiness($pdo,$config)['checks']);
    }finally{$pdo->query("SELECT RELEASE_LOCK('bois_p12_bootstrap')");}
}

function bois_p12_launch_checks(array $candidate,array $approved): array
{
    // Candidate remains closed. The separate proposed runtime is never installed.
    $checks=bois_production_config_checks($candidate);
    $checks['approved_runtime_identity']=($approved['mode']??null)==='production'&&($approved['db']??null)===($candidate['db']??null);
    foreach(bois_p8_readiness($approved)['checks'] as $key=>$ok) $checks['launch_'.$key]=$ok;
    $checks['launch_live_mode']=($approved['stripe_mode']??null)==='live';
    $checks['launch_checkout_enabled']=($approved['checkout_enabled']??null)===true;
    $checks['launch_production_config']=($approved['production_config_version']??null)===1;
    $checks['launch_admin_secret']=strlen((string)($approved['admin_token']??''))>=32&&($approved['admin_token']??'')!==($candidate['admin_token']??'');
    $base=(string)($approved['public_base_url']??'');
    $url=parse_url($base);
    $host=strtolower((string)($url['host']??''));
    $checks['launch_real_url']=($url['scheme']??'')==='https'&&$host!==''&&!str_ends_with($host,'.invalid')&&!str_ends_with($host,'.test')&&$host!=='localhost'&&!isset($url['user'])&&!isset($url['pass'])&&!isset($url['query'])&&!isset($url['fragment'])&&!str_contains($base,'bois-shop-p3')&&!str_contains($base,'bois-shop-stripe-sandbox');
    $origin='https://'.$host.(isset($url['port'])?':'.$url['port']:'');
    $checks['launch_origin']=($approved['allowed_origins']??null)===[$origin];
    return $checks+bois_production_decision_checks($approved);
}
