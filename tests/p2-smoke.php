<?php
declare(strict_types=1);

require dirname(__DIR__) . '/server/bootstrap.php';

$root=sys_get_temp_dir().'/bois-p2-test-'.bin2hex(random_bytes(4));
$config=[
    'mode'=>'test',
    'data_dir'=>$root,
    'admin_token'=>'test-token',
    'allowed_origins'=>[],
    'allowed_teams'=>['P9','F9','P13','F14','P16','Skridsko- & bandyskola 26/27'],
    'allowed_sizes'=>['128','140','152','164','XS','S'],
    'product_id'=>'match-kit-knatte',
    'product_label'=>'Matchställ Knatte',
    'supplier_code'=>'TEST-KIT',
    'order_period_id'=>'2026-27-matchstall',
    'order_period_label'=>'Matchställ 2026/27',
    'rate_limit_window'=>60,
    'rate_limit_max'=>20,
    'prices'=>['shirt'=>44900,'shorts'=>34900,'name_print'=>10000,'number_print'=>10000],
];

$payload=[
    'product_id'=>'match-kit-knatte',
    'order_period_id'=>'2026-27-matchstall',
    'team'=>'P13',
    'shirt_size'=>'140',
    'shorts_size'=>'140',
    'player_name'=>'Test Spelare',
    'number'=>'17',
    'name_print'=>true,
    'number_print'=>true,
    'parent_name'=>'Test Förälder',
    'email'=>'test@example.invalid',
    'phone'=>'070-000 00 00',
    'consent'=>true,
    'website'=>'',
    'idempotency_key'=>'p2-smoke-1',
];

try {
    $order=bois_create_order($config,$payload,['source'=>'p2-test']);

    if(($order['schema_version']??null)!==2) throw new RuntimeException('bad schema');
    if(($order['product_id']??'')!=='match-kit-knatte') throw new RuntimeException('bad product');
    if(($order['order_period_id']??'')!=='2026-27-matchstall') throw new RuntimeException('bad period');
    if(($order['supplier_code']??'')!=='TEST-KIT') throw new RuntimeException('bad supplier code');
    if(!bois_order_can_cancel($order)) throw new RuntimeException('new order should be cancelable');

    $public=bois_public_order($order);
    if(($public['cancelable']??false)!==true) throw new RuntimeException('public cancel flag failed');

    $cancelled=bois_cancel_order($config,$order['order_id'],$order['public_token']);
    if(($cancelled['status']??'')!=='cancelled') throw new RuntimeException('cancel failed');
    if(bois_order_can_cancel($cancelled)) throw new RuntimeException('cancelled order still cancelable');

    $summary=bois_summary_rows(bois_list_orders($config));
    if(count($summary)!==0) throw new RuntimeException('cancelled order should not be in summary');

    $invalidNumber=$payload;
    $invalidNumber['idempotency_key']='p2-bad-number';
    $invalidNumber['number']='0';
    try {
        bois_create_order($config,$invalidNumber,['source'=>'p2-test']);
        throw new RuntimeException('invalid number accepted');
    } catch (InvalidArgumentException) {}

    $invalidTeam=$payload;
    $invalidTeam['idempotency_key']='p2-bad-team';
    $invalidTeam['team']='Påhittat lag';
    try {
        bois_create_order($config,$invalidTeam,['source'=>'p2-test']);
        throw new RuntimeException('invalid team accepted');
    } catch (InvalidArgumentException) {}

    echo "P2_SMOKE: pass\n";
} finally {
    if(is_dir($root)){
        foreach(new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($root,FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        ) as $path){
            $path->isDir()?rmdir($path->getPathname()):unlink($path->getPathname());
        }
        @rmdir($root);
    }
}
