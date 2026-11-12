<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/server/p4_membership.php';
require_once dirname(__DIR__).'/server/p5_batch.php';
function p20_expect(bool $ok,string $message): void { if (!$ok) throw new RuntimeException($message); }
$calendar=['membership_period'=>'calendar_year'];
foreach (['2026-01-01','2026-12-31','2026-06-15'] as $day) {
    p20_expect(bois_p4_membership_end($calendar,new DateTimeImmutable($day))==='2026-12-31','Calendar end');
}
p20_expect(bois_p4_membership_end($calendar,new DateTimeImmutable('2026-12-31T23:30:00Z'))==='2027-12-31','Stockholm year boundary');
p20_expect(bois_p4_membership_end($calendar,new DateTimeImmutable('2026-03-01'),'2026-12-31')==='2026-12-31','Same-year renewal');
p20_expect(bois_p4_membership_end($calendar,new DateTimeImmutable('2026-03-01'),'2027-06-30')==='2027-06-30','Preserve future entitlement');
p20_expect(bois_p4_membership_end(['membership_validity_days'=>365],new DateTimeImmutable('2026-01-01'))==='2027-01-01','Legacy period');
$rows=[['quantity'=>1000,'item_created_at'=>'2020-01-01 00:00:00']];
$rule=['runtime_schedule'=>['mode'=>'monthly','day'=>null]];
p20_expect(bois_p5_due_reason($rule,$rows,false)===null,'Unknown day must block auto regardless of age or quantity');
p20_expect(bois_p5_due_reason($rule,$rows,true)==='MANUAL','Manual without monthly day');
$rule['runtime_schedule']['day']=31;
p20_expect(bois_p5_due_reason($rule,$rows,false,new DateTimeImmutable('2028-02-28T12:00:00Z'))===null,'Leap-year before cutoff');
p20_expect(bois_p5_due_reason($rule,$rows,false,new DateTimeImmutable('2028-02-29T12:00:00Z'))==='MONTHLY','Leap-year last day');
p20_expect(bois_p5_due_reason($rule,$rows,false,new DateTimeImmutable('2027-02-28T12:00:00Z'))==='MONTHLY','Nonleap last day');
$rule['runtime_schedule']['day']=1;
$rule['last_monthly_batch_at']='2026-10-31 23:30:00';
p20_expect(bois_p5_due_reason($rule,$rows,false,new DateTimeImmutable('2026-11-15'))===null,'UTC batch belongs to Stockholm November');
p20_expect(bois_p5_due_reason($rule,$rows,false,new DateTimeImmutable('2026-12-01'))==='MONTHLY','Next month');
$rule['runtime_schedule']['day']=0;
try { bois_p5_due_reason($rule,$rows,false); throw new RuntimeException('Bad day accepted'); } catch (InvalidArgumentException $e) {}
$config=require dirname(__DIR__).'/server/p20-preparation.example.php';
p20_expect($config['production_launch_enabled']===false && $config['checkout_enabled']===false,'Launch closed');
p20_expect($config['mail_transport']==='disabled' && $config['payment_mail_transport']==='disabled','Mail closed');
p20_expect($config['payment_provider']==='disabled' && $config['production_decisions']===[],'Payment/decisions closed');
p20_expect($config['retention']['apply_enabled']===false && $config['retention']['legal_hold']===true,'Deletion closed');
echo "P20_PREPARATION_BOUNDARIES: pass\n";
