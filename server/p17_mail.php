<?php
declare(strict_types=1);
require_once __DIR__.'/security.php';

// Transport adapters consume a rendered envelope. No network I/O in sink/test mode.
function bois_p17_address(string $address, bool $synthetic=false): string
{
    if (preg_match('/[\r\n\x00]/', $address) || !filter_var($address, FILTER_VALIDATE_EMAIL)
        || ($synthetic && !str_ends_with(strtolower($address), '@example.invalid'))) {
        throw new RuntimeException('mail_address_invalid');
    }
    return $address;
}

function bois_p17_render(array $message, string $queue): array
{
    $p=json_decode((string)$message['payload_json'], true, 32, JSON_THROW_ON_ERROR);
    if(!is_array($p))throw new RuntimeException('mail_payload_invalid');
    $kind=(string)($p['kind']??'');
    $subject=(string)$message['subject'];
    if($subject===''||strlen($subject)>512||preg_match('/[\r\n\x00]/',$subject))throw new RuntimeException('mail_subject_invalid');
    $attachments=[];
    $body="Tranås BoIS\n\n";
    if($queue==='supplier' && $kind==='SUPPLIER_BATCH'){
        $body.="Leverantörsorder – matchställ\nBatch: ".($p['batch_id']??'')."\n";
        $body.="Antal order: ".(int)($p['order_count']??0)."\nAntal matchställ: ".(int)($p['item_count']??0)."\n";
        $csv=base64_decode((string)($p['csv_base64']??''),true);
        $name=(string)($p['csv_filename']??'');
        if($csv===false||strlen($csv)>1024*1024||!preg_match('/\Atranas-bois-[a-z0-9-]+\.csv\z/',$name)
            ||!hash_equals((string)($p['csv_sha256']??''),hash('sha256',$csv)))throw new RuntimeException('mail_attachment_invalid');
        $attachments[]=['name'=>$name,'type'=>'text/csv; charset=UTF-8','content_base64'=>base64_encode($csv)];
        $body.="CSV-underlag bifogas. Kontrollsumma: ".hash('sha256',$csv)."\n";
    }elseif($queue==='payment' && in_array($kind,['PAYMENT_RECEIPT','PAYMENT_FAILED','PAYMENT_CANCELLED','REFUND_RECEIPT'],true)){
        $body.=match($kind){
            'PAYMENT_RECEIPT'=>"Orderbekräftelse och betalningskvitto\n",
            'PAYMENT_FAILED'=>"Betalningen misslyckades. Ingen betalning är bekräftad. Du kan försöka igen via din ordersida.\n",
            'PAYMENT_CANCELLED'=>"Betalningen avbröts. Du kan försöka igen via din ordersida.\n",
            'REFUND_RECEIPT'=>"Återbetalningsbekräftelse. Eventuell leverans/förmån granskas manuellt av föreningen.\n",
        };
        $amount=$p['amount_ore']??null;
        if(!is_int($amount)||$amount<0||($p['currency']??'')!=='SEK')throw new RuntimeException('mail_amount_invalid');
        $body.="Order: ".($p['order']??'')."\nStatus: ".($p['payment_status']??'')."\n";
        $body.="Belopp: ".number_format($amount/100,2,',',' ')." SEK\n";
    }else throw new RuntimeException('mail_template_unknown');
    if(strlen($body)>16384||str_contains($body,"\0"))throw new RuntimeException('mail_body_invalid');
    return ['idempotency_key'=>$queue.':'.(string)$message['message_key'],
        'to'=>bois_p17_address((string)$message['to_email']),
        'cc'=>empty($message['cc_email'])?null:bois_p17_address((string)$message['cc_email']),
        'subject'=>$subject,'text'=>$body,'attachments'=>$attachments];
}

function bois_p17_sink_dir(array $config): string
{
    $mail=$config['mail']??[];
    // Reuse the established private-path/permissions boundary, not its log files.
    return bois_p15_storage(['observability'=>['enabled'=>true,
        'log_dir'=>$mail['sink_dir']??'', 'public_root'=>$mail['public_root']??'']]);
}

function bois_p17_sink(array $envelope, array $config): bool
{
    $dir=bois_p17_sink_dir($config);
    $path=$dir.'/'.hash('sha256',$envelope['idempotency_key']).'.json';
    $json=json_encode($envelope,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
    if(!bois_p15_disk_ok((float)disk_free_space($dir),(float)disk_total_space($dir),true))throw new RuntimeException('mail_sink_unavailable');
    clearstatcache(true,$path);
    if(is_link($path)||(file_exists($path)&&(!is_file($path)||(fileperms($path)&0077)!==0||filesize($path)>2*1024*1024)))throw new RuntimeException('mail_sink_refused');
    $old=umask(0077);
    try{$f=@fopen($path,'c+b');}finally{umask($old);}
    if($f===false)throw new RuntimeException('mail_sink_unavailable');
    try{
        if(!flock($f,LOCK_EX))throw new RuntimeException('mail_sink_unavailable');
        $stat=fstat($f);$named=lstat($path);
        if(!$stat||!$named||$stat['ino']!==$named['ino']||$stat['nlink']!==1||($stat['mode']&0077)!==0)throw new RuntimeException('mail_sink_refused');
        $existing=stream_get_contents($f);
        if($existing!==''){
            if(!hash_equals(hash('sha256',$existing),hash('sha256',$json)))throw new RuntimeException('mail_sink_key_conflict');
            return true;
        }
        if(fwrite($f,$json)!==strlen($json)||!fflush($f)||!fsync($f))throw new RuntimeException('mail_sink_unavailable');
        return true;
    }finally{fclose($f);}
}

function bois_p17_mime(array $e, string $from): array
{
    $headers=['From: '.bois_p17_address($from),'MIME-Version: 1.0','X-Mailer: Tranås-BoIS-Shop'];
    if($e['cc']!==null)$headers[]='Cc: '.$e['cc'];
    // Stable Message-ID assists reconciliation; it is not an exactly-once SMTP guarantee.
    $domain=substr($from,strrpos($from,'@')+1);
    $headers[]='Message-ID: <'.hash('sha256',$e['idempotency_key']).'@'.$domain.'>';
    $boundary='bois-'.bin2hex(random_bytes(16));
    $headers[]='Content-Type: multipart/mixed; boundary="'.$boundary.'"';
    $body='--'.$boundary."\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n";
    $body.=chunk_split(base64_encode($e['text']),76,"\r\n");
    foreach($e['attachments'] as $a){
        $body.='--'.$boundary."\r\nContent-Type: ".$a['type']."\r\nContent-Disposition: attachment; filename=\"".$a['name']."\"\r\nContent-Transfer-Encoding: base64\r\n\r\n";
        $body.=chunk_split($a['content_base64'],76,"\r\n");
    }
    $body.='--'.$boundary."--\r\n";
    return ['headers'=>implode("\r\n",$headers),'body'=>$body,
        'subject'=>'=?UTF-8?B?'.base64_encode($e['subject']).'?='];
}

function bois_p17_sender(array $config, string $transport, string $queue, ?callable $testSender=null): ?callable
{
    $production=($config['mode']??'')==='production';
    if($production && $testSender!==null)throw new RuntimeException('mail_test_adapter_refused');
    if($testSender===null && $transport==='disabled')return null;
    if($testSender===null && !in_array($transport,['sink','php_mail'],true))throw new RuntimeException('mail_transport_unknown');
    $mail=$config['mail']??[];
    if($transport==='php_mail' && $testSender===null){
        if(!$production||($config['production_launch_enabled']??false)!==true
            ||($mail['external_delivery_approved']??false)!==true||($mail['domain_verified']??false)!==true
            ||($mail['provider_verified']??false)!==true)throw new RuntimeException('mail_activation_closed');
        bois_p17_address((string)($mail['from']??''));
    }elseif($production)throw new RuntimeException('mail_synthetic_adapter_refused');
    if($transport==='sink' && $testSender===null)bois_p17_sink_dir($config);
    return function(array $message) use($config,$transport,$queue,$testSender,$mail):bool {
        $e=bois_p17_render($message,$queue);
        if($testSender!==null || $transport==='sink'){
            bois_p17_address($e['to'],true);
            if($e['cc']!==null)bois_p17_address($e['cc'],true);
            return $testSender!==null ? (bool)$testSender($message) : bois_p17_sink($e,$config);
        }
        if(str_ends_with(strtolower($e['to']),'@example.invalid')||($e['cc']!==null&&str_ends_with(strtolower($e['cc']),'@example.invalid')))throw new RuntimeException('mail_external_recipient_invalid');
        $mime=bois_p17_mime($e,(string)$mail['from']);
        return mail($e['to'],$mime['subject'],$mime['body'],$mime['headers']);
    };
}
