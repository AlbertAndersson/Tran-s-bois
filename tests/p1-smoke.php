<?php
declare(strict_types=1);
require dirname(__DIR__) . '/server/bootstrap.php';
$root=sys_get_temp_dir().'/bois-p1-test-'.bin2hex(random_bytes(4));
$config=['mode'=>'test','data_dir'=>$root,'admin_token'=>'test-token','allowed_origins'=>[],'rate_limit_window'=>60,'rate_limit_max'=>20,'prices'=>['shirt'=>44900,'shorts'=>34900,'name_print'=>10000,'number_print'=>10000]];
$payload=['team'=>'P9','shirt_size'=>'140','shorts_size'=>'140','player_name'=>'Test Spelare','number'=>'10','name_print'=>true,'number_print'=>true,'parent_name'=>'Test Förälder','email'=>'test@example.invalid','phone'=>'070-000 00 00','consent'=>true,'website'=>'','idempotency_key'=>'smoke-1'];
try {
  $order=bois_create_order($config,$payload,['source'=>'test']);
  if(!preg_match('/^BOIS-[0-9]{6}-[A-Z0-9]{5}$/',$order['order_id'])) throw new RuntimeException('bad order id');
  if($order['total_ore']!==99800) throw new RuntimeException('bad total');
  $again=bois_create_order($config,$payload,['source'=>'test']);
  if($again['order_id']!==$order['order_id']) throw new RuntimeException('idempotency failed');
  $updated=bois_update_status($config,$order['order_id'],'ready_for_supplier');
  if($updated['status']!=='ready_for_supplier') throw new RuntimeException('status update failed');
  $list=bois_list_orders($config,['team'=>'P9']);
  if(count($list)!==1) throw new RuntimeException('list failed');
  echo "P1_SMOKE: pass\n";
} finally {
  if(is_dir($root)){foreach(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST) as $p){$p->isDir()?rmdir($p->getPathname()):unlink($p->getPathname());}@rmdir($root);}
}
