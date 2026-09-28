<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/server/p3_db.php';
require_once dirname(__DIR__) . '/server/p5_batch.php';
require_once dirname(__DIR__) . '/server/p4_membership.php';
require_once dirname(__DIR__) . '/server/p6_payment.php';
require_once dirname(__DIR__) . '/server/p7_assortment.php';
require_once dirname(__DIR__) . '/server/p8_stripe.php';
require_once dirname(__DIR__) . '/server/p9_sales.php';

$config=[
    'mode'=>'test',
    'admin_token'=>'p9-admin',
    'allowed_origins'=>[],
    'mail_transport'=>'disabled',
    'payment_mail_transport'=>'disabled',
    'membership_validity_days'=>365,
    'payment_provider'=>'mock',
    'payment_webhook_secret'=>str_repeat('p9-secret-',4),
    'stripe_mode'=>'test',
    'stripe_secret_key'=>'',
    'stripe_webhook_secret'=>'',
    'stripe_swish_enabled'=>false,
    'public_base_url'=>'https://example.test/shop',
    'merchant_verified'=>false,
    'stripe_fees_approved'=>false,
    'refund_policy_approved'=>false,
    'production_launch_enabled'=>false,
    'sales_tracking_enabled'=>true,
    'db'=>[
        'host'=>getenv('BOIS_P3_TEST_DB_HOST') ?: '127.0.0.1',
        'port'=>(int)(getenv('BOIS_P3_TEST_DB_PORT') ?: 3306),
        'database'=>getenv('BOIS_P3_TEST_DB_NAME') ?: 'bois_p9_test',
        'user'=>getenv('BOIS_P3_TEST_DB_USER') ?: 'root',
        'password'=>getenv('BOIS_P3_TEST_DB_PASSWORD') ?: 'root',
    ],
];

$pdo=bois_p3_pdo($config);
bois_p3_apply_schema($pdo);
bois_p3_seed_catalog($pdo);
bois_p5_apply_schema($pdo);
bois_p4_apply_schema($pdo);
bois_p6_apply_schema($pdo);
bois_p7_apply_schema($pdo);
bois_p8_apply_schema($pdo);
bois_p9_apply_schema($pdo);

$session='123e4567-e89b-42d3-a456-426614174000';
$attr=[
    'source'=>'facebook',
    'medium'=>'social',
    'campaign'=>'hostkampanj-2026',
    'ref'=>'p13',
    'landing_path'=>'/bois-shop-p3/',
];

$first=bois_p9_capture_event($pdo,[
    'session_id'=>$session,
    'event_key'=>'evt-p9-page-0001',
    'event_type'=>'page_view',
    'page_path'=>'/bois-shop-p3/',
    'attribution'=>$attr,
],$config);
if(!$first['accepted'] || $first['duplicate']) throw new RuntimeException('Initial P9 event failed.');

$duplicate=bois_p9_capture_event($pdo,[
    'session_id'=>$session,
    'event_key'=>'evt-p9-page-0001',
    'event_type'=>'page_view',
    'page_path'=>'/bois-shop-p3/',
    'attribution'=>$attr,
],$config);
if(!$duplicate['duplicate']) throw new RuntimeException('P9 event dedupe failed.');

bois_p9_capture_event($pdo,[
    'session_id'=>$session,
    'event_key'=>'evt-p9-product-0001',
    'event_type'=>'product_view',
    'page_path'=>'/bois-shop-p3/membership.html',
    'product_key'=>'membership',
    'attribution'=>$attr,
],$config);
bois_p9_capture_event($pdo,[
    'session_id'=>$session,
    'event_key'=>'evt-p9-checkout-view-0001',
    'event_type'=>'checkout_view',
    'page_path'=>'/bois-shop-p3/payment.html',
    'attribution'=>$attr,
],$config);
bois_p9_capture_event($pdo,[
    'session_id'=>$session,
    'event_key'=>'evt-p9-checkout-started-0001',
    'event_type'=>'checkout_started',
    'page_path'=>'/bois-shop-p3/payment.html',
    'attribution'=>$attr,
],$config);

$order=bois_p3_create_order($pdo,[
    'customer'=>[
        'name'=>'P9 Synthetic',
        'email'=>'p9@example.invalid',
        'phone'=>'070-900 00 00',
    ],
    'items'=>[
        ['sku'=>'MEM-ADULT','quantity'=>1,'metadata'=>['member_name'=>'P9 Synthetic']],
        ['sku'=>'NW-GYM-ANNUAL','quantity'=>1,'metadata'=>[]],
    ],
    'existing_member'=>false,
    'consent'=>true,
    'website'=>'',
    'idempotency_key'=>'p9-sales-order',
]);
bois_p4_register_order($pdo,$order['public_id']);
bois_p9_link_order($pdo,$order['public_id'],[
    'sales_session_id'=>$session,
    'sales_attribution'=>$attr,
],$config);

$checkout=bois_p6_checkout($pdo,$config,$order['public_id'],$order['public_token'],'card');
$paid=bois_p6_mock_event($pdo,$config,$checkout['session_ref'],$checkout['session_token'],'paid');
if(($paid['result']['status']??'')!=='PAID') throw new RuntimeException('P9 synthetic paid flow failed.');

$dashboard=bois_p9_admin_dashboard($pdo);
$k=$dashboard['kpis'];
if($k['sessions']!==1) throw new RuntimeException('P9 session KPI mismatch.');
if($k['sessions_with_orders']!==1 || $k['paid_sessions']!==1 || $k['paid_orders']!==1){
    throw new RuntimeException('P9 order funnel KPI mismatch.');
}
if($k['session_to_paid_pct']!==100.0) throw new RuntimeException('P9 conversion mismatch.');
if($k['net_paid_ore']!==300000) throw new RuntimeException('P9 net paid value mismatch.');
if(($dashboard['funnel']['page_view']??0)!==1 ||
   ($dashboard['funnel']['product_view']??0)!==1 ||
   ($dashboard['funnel']['checkout_view']??0)!==1 ||
   ($dashboard['funnel']['checkout_started']??0)!==1 ||
   ($dashboard['funnel']['order_created']??0)!==1 ||
   ($dashboard['funnel']['paid']??0)!==1){
    throw new RuntimeException('P9 funnel stages mismatch.');
}

$campaign=$dashboard['campaigns'][0]??[];
if(($campaign['source']??'')!=='facebook' ||
   ($campaign['campaign']??'')!=='hostkampanj-2026' ||
   (int)($campaign['paid_orders']??0)!==1 ||
   (int)($campaign['net_paid_ore']??0)!==300000){
    throw new RuntimeException('P9 campaign attribution mismatch.');
}

$products=$dashboard['products'];
$skus=array_column($products,'sku');
if(!in_array('MEM-ADULT',$skus,true) || !in_array('NW-GYM-ANNUAL',$skus,true)){
    throw new RuntimeException('P9 product mix missing paid SKUs.');
}

$recs=bois_p9_recommendations($pdo,['skus'=>['MEM-ADULT'],'existing_member'=>false]);
if(count($recs)!==1 || ($recs[0]['product_key']??'')!=='nordic-gym' || (int)($recs[0]['discount_ore']??-1)!==0){
    throw new RuntimeException('P9 membership cross-sell recommendation failed.');
}
$eligibility=bois_p9_recommendations($pdo,['skus'=>['NW-GYM-ANNUAL'],'existing_member'=>false]);
if(count($eligibility)!==1 || ($eligibility[0]['kind']??'')!=='eligibility'){
    throw new RuntimeException('P9 gym eligibility recommendation failed.');
}

$privacy=$dashboard['privacy'];
if(!$privacy['first_party_only'] ||
   $privacy['direct_customer_identifiers_stored'] ||
   !$privacy['pseudonymous_session_identifier'] ||
   !$privacy['order_link_exists'] ||
   $privacy['ip_stored'] ||
   $privacy['user_agent_stored'] ||
   $privacy['external_analytics']){
    throw new RuntimeException('P9 privacy invariant failed.');
}

// Read by position: information_schema column-label casing differs by driver.
$columns=$pdo->query(
    "SELECT COLUMN_NAME FROM information_schema.columns
     WHERE TABLE_SCHEMA=DATABASE()
       AND TABLE_NAME IN ('bois_sales_sessions','bois_sales_events','bois_sales_order_links')"
)->fetchAll(PDO::FETCH_COLUMN);
$columnNames=array_map('strtolower',$columns);
if(!$columnNames || !in_array('session_id',$columnNames,true) || !in_array('order_id',$columnNames,true)){
    throw new RuntimeException('Sales schema inspection returned incomplete metadata.');
}
foreach(['name','email','phone','ip','ip_address','user_agent'] as $forbidden){
    if(in_array($forbidden,$columnNames,true)) throw new RuntimeException('Forbidden sales PII column: '.$forbidden);
}

// OFF means zero writes, including attempts to re-attribute an existing order.
$snapshot=function() use($pdo): string {
    $data=[];
    foreach(['bois_sales_sessions','bois_sales_events','bois_sales_order_links'] as $table){
        $data[$table]=$pdo->query('SELECT * FROM '.$table.' ORDER BY 1')->fetchAll();
    }
    return json_encode($data,JSON_THROW_ON_ERROR);
};
$before=$snapshot();
$forged=[
    'session_id'=>'123e4567-e89b-42d3-a456-426614174999',
    'sales_session_id'=>'123e4567-e89b-42d3-a456-426614174999',
    'event_key'=>'evt-off-must-not-exist',
    'event_type'=>'page_view',
    'attribution'=>$attr,
    'sales_attribution'=>$attr,
    'sales_tracking_enabled'=>true,
    'mode'=>'staging',
    'consent'=>true,
];
foreach([
    [],
    ['mode'=>'staging'],
    ['mode'=>'staging','sales_tracking_enabled'=>false],
    ['mode'=>'staging','sales_tracking_enabled'=>'true'],
    ['mode'=>'staging','sales_tracking_enabled'=>1],
    ['mode'=>'production','sales_tracking_enabled'=>true],
    ['mode'=>'unknown','sales_tracking_enabled'=>true],
] as $off){
    if(bois_p9_tracking_enabled($off)) throw new RuntimeException('Unexpected tracking approval.');
    $result=bois_p9_capture_event($pdo,$forged,$off);
    if(($result['disabled']??false)!==true) throw new RuntimeException('Event not disabled.');
    bois_p9_link_order($pdo,$order['public_id'],$forged,$off);
    if($snapshot()!==$before) throw new RuntimeException('Disabled tracking changed sales data.');
}
bois_p9_capture_event($pdo,$forged);
bois_p9_link_order($pdo,$order['public_id'],$forged);
if($snapshot()!==$before) throw new RuntimeException('Missing config allowed sales writes.');

$offOrder=bois_p3_create_order($pdo,[
    'customer'=>['name'=>'No Tracking Test','email'=>'no-tracking@example.invalid'],
    'items'=>[['sku'=>'MEM-ADULT','quantity'=>1,'metadata'=>['member_name'=>'No Tracking Test']]],
    'consent'=>true,'website'=>'','idempotency_key'=>'p9-no-tracking-order',
]+$forged);
$offConfig=array_replace($config,['sales_tracking_enabled'=>false]);
bois_p4_register_order($pdo,$offOrder['public_id']);
bois_p9_link_order($pdo,$offOrder['public_id'],$forged,$offConfig);
$offCheckout=bois_p6_checkout($pdo,$offConfig,$offOrder['public_id'],$offOrder['public_token'],'card');
$offPaid=bois_p6_mock_event($pdo,$offConfig,$offCheckout['session_ref'],$offCheckout['session_token'],'paid');
if(($offPaid['result']['status']??'')!=='PAID') throw new RuntimeException('Order/payment requires tracking.');
if($snapshot()!==$before) throw new RuntimeException('Untracked order wrote sales data.');

echo "P9_SALES_ENGINE: pass\n";
echo "FIRST_PARTY_FUNNEL: pass\n";
echo "CAMPAIGN_ATTRIBUTION: pass\n";
echo "ORDER_LINK: pass\n";
echo "PAID_CONVERSION: pass\n";
echo "PRODUCT_MIX: pass\n";
echo "ZERO_DISCOUNT_RECOMMENDATIONS: pass\n";
echo "DIRECT_CUSTOMER_IDENTIFIERS_IN_SALES_TABLES: no\n";
echo "PSEUDONYMOUS_SESSION_DATA: yes\n";
echo "TRACKING_OFF_ZERO_SALES_WRITES: pass\n";
echo "PRODUCTION_TRACKING_BLOCKED: pass\n";
echo "ORDER_AND_MOCK_PAYMENT_WITHOUT_TRACKING: pass\n";
echo "EXTERNAL_ANALYTICS: no\n";
echo "EXTERNAL_EMAIL: no\n";
echo "REAL_PAYMENT: no\n";
echo "NEW_EXTERNAL_COST: 0\n";
