<?php
declare(strict_types=1);
require_once __DIR__ . "/security.php";
require __DIR__ . '/bootstrap.php';
$config=bois_load_config();
if(($config['mode']??'')!=='staging'){fwrite(STDERR,"Refusing to seed outside staging.\n");exit(2);}
if(count(bois_list_orders($config))>0){echo "Seed skipped: orders already exist.\n";exit(0);}
$rows=[
 ['team'=>'P9','shirt_size'=>'140','shorts_size'=>'140','player_name'=>'Testspelare Ett','number'=>'10','name_print'=>true,'number_print'=>true,'parent_name'=>'Testförälder Ett','email'=>'test1@example.invalid','phone'=>'070-000 00 01','consent'=>true,'website'=>'','idempotency_key'=>'seed-1'],
 ['team'=>'P9','shirt_size'=>'152','shorts_size'=>'152','player_name'=>'Testspelare Två','number'=>'17','name_print'=>true,'number_print'=>true,'parent_name'=>'Testförälder Två','email'=>'test2@example.invalid','phone'=>'070-000 00 02','consent'=>true,'website'=>'','idempotency_key'=>'seed-2'],
 ['team'=>'F9','shirt_size'=>'128','shorts_size'=>'140','player_name'=>'Testspelare Tre','number'=>'8','name_print'=>false,'number_print'=>true,'parent_name'=>'Testförälder Tre','email'=>'test3@example.invalid','phone'=>'070-000 00 03','consent'=>true,'website'=>'','idempotency_key'=>'seed-3'],
];
foreach($rows as $row){$o=bois_create_order($config,$row,['source'=>'seed']);echo $o['order_id']."\n";}
echo "Seed complete.\n";
