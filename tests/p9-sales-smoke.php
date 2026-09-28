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
]);
if(!$first['accepted'] || $first['duplicate']) throw new RuntimeException('Initial P9 event failed.');

$duplicate=bois_p9_capture_event($pdo,[
    'session_id'=>$session,
    'event_key'=>'evt-p9-page-0001',
    'event_type'=>'page_view',
    'page_path'=>'/bois-shop-p3/',
    'attribution'=>$attr,
]);
if(!$duplicate['duplicate']) throw new RuntimeException('P9 event dedupe failed.');

bois_p9_capture_event($pdo,[
    'session_id'=>$session,
    'event_key'=>'evt-p9-product-0001',
    'event_type'=>'product_view',
    'page_path'=>'/bois-shop-p3/membership.html',
    'product_key'=>'membership',
    'attribution'=>$attr,
]);
bois_p9_capture_event($pdo,[
    'session_id'=>$session,
    'event_key'=>'evt-p9-checkout-view-0001',
    'event_type'=>'checkout_view',
    'page_path'=>'/bois-shop-p3/payment.html',
    'attribution'=>$attr,
]);
bois_p9_capture_event($pdo,[
    'session_id'=>$session,
    'event_key'=>'evt-p9-checkout-started-0001',
    'event_type'=>'checkout_started',
    'page_path'=>'/bois-shop-p3/payment.html',
    'attribution'=>$attr,
]);

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
]);

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

$columns=$pdo->query(
    "SELECT table_name,column_name FROM information_schema.columns
     WHERE table_schema=DATABASE() AND table_name LIKE 'bois_sales_%'"
)->fetchAll();
$columnNames=array_map(fn($r)=>strtolower((string)$r['column_name']),$columns);
foreach(['name','email','phone','ip','ip_address','user_agent'] as $forbidden){
    if(in_array($forbidden,$columnNames,true)) throw new RuntimeException('Forbidden sales PII column: '.$forbidden);
}

echo "P9_SALES_ENGINE: pass\n";
echo "FIRST_PARTY_FUNNEL: pass\n";
echo "CAMPAIGN_ATTRIBUTION: pass\n";
echo "ORDER_LINK: pass\n";
echo "PAID_CONVERSION: pass\n";
echo "PRODUCT_MIX: pass\n";
echo "ZERO_DISCOUNT_RECOMMENDATIONS: pass\n";
echo "DIRECT_CUSTOMER_IDENTIFIERS_IN_SALES_TABLES: no\n";
echo "PSEUDONYMOUS_SESSION_DATA: yes\n";
echo "EXTERNAL_ANALYTICS: no\n";
echo "EXTERNAL_EMAIL: no\n";
echo "REAL_PAYMENT: no\n";
echo "NEW_EXTERNAL_COST: 0\n";
