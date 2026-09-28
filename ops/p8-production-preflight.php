<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/server/p3_db.php';
require_once dirname(__DIR__) . '/server/p5_batch.php';
require_once dirname(__DIR__) . '/server/p4_membership.php';
require_once dirname(__DIR__) . '/server/p6_payment.php';
require_once dirname(__DIR__) . '/server/p7_assortment.php';
require_once dirname(__DIR__) . '/server/p8_stripe.php';

try{
    $configPath=$argv[1]??'';
    if($configPath==='' || !is_file($configPath)) throw new RuntimeException('Production config path missing.');
    $config=require $configPath;
    if(!is_array($config)) throw new RuntimeException('Production config invalid.');

    $readiness=bois_p8_readiness($config);
    if(!$readiness['ready_for_production_launch']){
        $missing=[];
        foreach($readiness['checks'] as $name=>$ok) if(!$ok) $missing[]=$name;
        throw new RuntimeException('Production readiness blocked: '.implode(',',$missing));
    }

    $base=bois_p8_public_base_url($config);
    if(str_contains($base,'example.test') || str_contains($base,'/bois-shop-p3')){
        throw new RuntimeException('Staging/example base URL is not allowed in production.');
    }

    $admin=(string)($config['admin_token']??'');
    if(strlen($admin)<32) throw new RuntimeException('Production admin token is too short.');

    $origin=parse_url($base,PHP_URL_SCHEME).'://'.parse_url($base,PHP_URL_HOST);
    $port=parse_url($base,PHP_URL_PORT);
    if($port) $origin.=':'.$port;
    if(!in_array($origin,(array)($config['allowed_origins']??[]),true)){
        throw new RuntimeException('Production origin is not allowlisted.');
    }

    $pdo=bois_p3_pdo($config);
    $db=(string)$pdo->query('SELECT DATABASE()')->fetchColumn();
    if($db==='') throw new RuntimeException('Production DB not selected.');

    $tables=$pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
    $foreign=array_values(array_filter($tables,fn($t)=>!str_starts_with((string)$t,'bois_')));
    if($foreign) throw new RuntimeException('Production DB contains non-BoIS tables.');
    if(count($tables)!==20) throw new RuntimeException('Expected 20 BoIS tables after P8 migrations.');

    $stmt=$pdo->prepare("SELECT COUNT(*) FROM bois_schema_migrations WHERE version=?");
    $stmt->execute(['20260928_p8_stripe_v1']);
    if((int)$stmt->fetchColumn()!==1) throw new RuntimeException('P8 migration ledger entry missing.');

    echo "P8_PRODUCTION_PREFLIGHT: pass\n";
    echo "PAYMENT_PROVIDER: stripe\n";
    echo "STRIPE_MODE: live\n";
    echo "PRODUCTION_LAUNCH_GATE: approved\n";
    echo "BOIS_TABLES: 20\n";
    echo "FOREIGN_TABLES: 0\n";
    echo "P8_LEDGER: complete\n";
    echo "SECRETS_EXPOSED: no\n";
}catch(Throwable $e){
    fwrite(STDERR,'P8 production preflight failed: '.$e->getMessage()."\n");
    exit(1);
}
