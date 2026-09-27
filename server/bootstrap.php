<?php
declare(strict_types=1);

const BOIS_SCHEMA_VERSION = 2;

function bois_load_config(): array
{
    $path = getenv('BOIS_CONFIG_PATH');
    if (!is_string($path) || $path === '') {
        $path = dirname(__DIR__, 2) . '/.bois-p1/config.php';
    }
    if (!is_file($path)) {
        throw new RuntimeException('Runtime configuration is missing.');
    }

    $config = require $path;
    if (!is_array($config)) {
        throw new RuntimeException('Runtime configuration is invalid.');
    }

    foreach (['data_dir', 'admin_token', 'mode'] as $required) {
        if (!isset($config[$required]) || !is_string($config[$required]) || trim($config[$required]) === '') {
            throw new RuntimeException("Runtime configuration missing: {$required}");
        }
    }

    $config['allowed_origins'] = array_values(array_filter($config['allowed_origins'] ?? [], 'is_string'));
    $config['prices'] = array_merge([
        'shirt' => 44900,
        'shorts' => 34900,
        'name_print' => 10000,
        'number_print' => 10000,
    ], $config['prices'] ?? []);
    $config['allowed_teams'] = array_values(array_filter($config['allowed_teams'] ?? [
        'P9','F9','P13','F14','P16','Skridsko- & bandyskola 26/27'
    ], 'is_string'));
    $config['allowed_sizes'] = array_values(array_filter($config['allowed_sizes'] ?? [
        '128','140','152','164','XS','S'
    ], 'is_string'));
    $config['product_id'] = (string)($config['product_id'] ?? 'match-kit-knatte');
    $config['product_label'] = (string)($config['product_label'] ?? 'Matchställ Knatte');
    $config['supplier_code'] = (string)($config['supplier_code'] ?? '');
    $config['order_period_id'] = (string)($config['order_period_id'] ?? '2026-27-matchstall');
    $config['order_period_label'] = (string)($config['order_period_label'] ?? 'Matchställ 2026/27');
    $config['rate_limit_window'] = max(10, (int)($config['rate_limit_window'] ?? 60));
    $config['rate_limit_max'] = max(1, (int)($config['rate_limit_max'] ?? 20));

    return $config;
}

function bois_prepare_storage(array $config): void
{
    $root = rtrim($config['data_dir'], '/');
    foreach ([$root, "$root/orders", "$root/rate"] as $dir) {
        if (!is_dir($dir) && !mkdir($dir, 0700, true) && !is_dir($dir)) {
            throw new RuntimeException('Could not prepare order storage.');
        }
        @chmod($dir, 0700);
    }
}

function bois_clean_string(mixed $value, int $max): string
{
    if (!is_string($value)) return '';
    $value = trim(preg_replace('/\s+/u', ' ', $value) ?? '');
    return function_exists('mb_substr') ? mb_substr($value, 0, $max) : substr($value, 0, $max);
}

function bois_text_length(string $value): int
{
    return function_exists('mb_strlen') ? mb_strlen($value) : strlen($value);
}

function bois_bool(mixed $value): bool
{
    return $value === true || $value === 1 || $value === '1' || $value === 'true' || $value === 'on';
}

function bois_validate_order(array $input, array $config): array
{
    if (bois_clean_string($input['website'] ?? '', 200) !== '') {
        throw new InvalidArgumentException('Beställningen kunde inte tas emot.');
    }

    $team = bois_clean_string($input['team'] ?? '', 60);
    $shirt = bois_clean_string($input['shirt_size'] ?? '', 10);
    $shorts = bois_clean_string($input['shorts_size'] ?? '', 10);
    $player = bois_clean_string($input['player_name'] ?? '', 80);
    $numberRaw = bois_clean_string($input['number'] ?? '', 2);
    $parent = bois_clean_string($input['parent_name'] ?? '', 80);
    $email = strtolower(bois_clean_string($input['email'] ?? '', 120));
    $phone = bois_clean_string($input['phone'] ?? '', 25);
    $configProductId = (string)($config['product_id'] ?? 'match-kit-knatte');
    $configProductLabel = (string)($config['product_label'] ?? 'Matchställ Knatte');
    $configSupplierCode = (string)($config['supplier_code'] ?? '');
    $configPeriodId = (string)($config['order_period_id'] ?? '2026-27-matchstall');
    $configPeriodLabel = (string)($config['order_period_label'] ?? 'Matchställ 2026/27');
    $allowedTeams = array_values(array_filter($config['allowed_teams'] ?? [
        'P9','F9','P13','F14','P16','Skridsko- & bandyskola 26/27'
    ], 'is_string'));
    $allowedSizes = array_values(array_filter($config['allowed_sizes'] ?? [
        '128','140','152','164','XS','S'
    ], 'is_string'));

    $productId = bois_clean_string($input['product_id'] ?? $configProductId, 80);
    $periodId = bois_clean_string($input['order_period_id'] ?? $configPeriodId, 80);

    if (!in_array($team, $allowedTeams, true)) throw new InvalidArgumentException('Välj ett giltigt lag.');
    if (!in_array($shirt, $allowedSizes, true) || !in_array($shorts, $allowedSizes, true)) throw new InvalidArgumentException('Välj giltiga storlekar.');
    if ($productId !== $configProductId) throw new InvalidArgumentException('Välj en giltig produkt.');
    if ($periodId !== $configPeriodId) throw new InvalidArgumentException('Beställningsperioden är inte giltig.');
    if (bois_text_length($player) < 2) throw new InvalidArgumentException('Ange spelarens namn.');
    if (!preg_match('/^\d{1,2}$/', $numberRaw)) throw new InvalidArgumentException('Tröjnumret ska vara 1–2 siffror.');

    $numberInt = (int)$numberRaw;
    if ($numberInt < 1 || $numberInt > 99) throw new InvalidArgumentException('Tröjnumret ska vara mellan 1 och 99.');

    if (bois_text_length($parent) < 2) throw new InvalidArgumentException('Ange förälders namn.');
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) throw new InvalidArgumentException('Ange en giltig e-postadress.');
    if (!preg_match('/^[0-9+() .\-]{7,25}$/', $phone)) throw new InvalidArgumentException('Ange ett giltigt telefonnummer.');
    if (!bois_bool($input['consent'] ?? false)) throw new InvalidArgumentException('Godkännande krävs för att registrera beställningen.');

    $namePrint = bois_bool($input['name_print'] ?? false);
    $numberPrint = bois_bool($input['number_print'] ?? false);
    $prices = $config['prices'];
    $total = (int)$prices['shirt'] + (int)$prices['shorts']
        + ($namePrint ? (int)$prices['name_print'] : 0)
        + ($numberPrint ? (int)$prices['number_print'] : 0);

    return [
        'product_id' => $configProductId,
        'product_label' => $configProductLabel,
        'supplier_code' => $configSupplierCode,
        'order_period_id' => $configPeriodId,
        'order_period_label' => $configPeriodLabel,
        'team' => $team,
        'shirt_size' => $shirt,
        'shorts_size' => $shorts,
        'player_name' => $player,
        'number' => (string)$numberInt,
        'name_print' => $namePrint,
        'number_print' => $numberPrint,
        'parent_name' => $parent,
        'email' => $email,
        'phone' => $phone,
        'total_ore' => $total,
        'currency' => 'SEK',
        'consent' => true,
        'idempotency_key' => bois_clean_string($input['idempotency_key'] ?? '', 80),
    ];
}

function bois_order_path(array $config, string $orderId): string
{
    if (!preg_match('/^BOIS-[0-9]{6}-[A-Z0-9]{5}$/', $orderId)) throw new InvalidArgumentException('Ogiltigt ordernummer.');
    return rtrim($config['data_dir'], '/') . '/orders/' . $orderId . '.json';
}

function bois_read_json_file(string $path): array
{
    $raw = @file_get_contents($path);
    if ($raw === false) throw new RuntimeException('Ordern kunde inte läsas.');
    $data = json_decode($raw, true, 64, JSON_THROW_ON_ERROR);
    if (!is_array($data)) throw new RuntimeException('Orderfilen är ogiltig.');
    return $data;
}

function bois_atomic_write_json(string $path, array $data): void
{
    $dir = dirname($path);
    $tmp = tempnam($dir, '.tmp-');
    if ($tmp === false) throw new RuntimeException('Kunde inte skapa temporär orderfil.');

    $encoded = json_encode($data, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR) . "\n";
    if (file_put_contents($tmp, $encoded, LOCK_EX) === false) {
        @unlink($tmp);
        throw new RuntimeException('Kunde inte skriva orderfil.');
    }
    @chmod($tmp, 0600);

    if (!rename($tmp, $path)) {
        @unlink($tmp);
        throw new RuntimeException('Kunde inte slutföra orderfil.');
    }
}

function bois_find_idempotent(array $config, string $key): ?array
{
    if ($key === '') return null;

    foreach (glob(rtrim($config['data_dir'], '/') . '/orders/*.json') ?: [] as $file) {
        try { $order = bois_read_json_file($file); } catch (Throwable) { continue; }
        if (($order['idempotency_key'] ?? '') === $key) return $order;
    }
    return null;
}

function bois_create_order(array $config, array $input, array $meta=[]): array
{
    bois_prepare_storage($config);
    $clean = bois_validate_order($input, $config);

    if ($existing = bois_find_idempotent($config, $clean['idempotency_key'])) return $existing;

    date_default_timezone_set('Europe/Stockholm');
    do {
        $id = 'BOIS-' . date('ymd') . '-' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 5));
        $path = bois_order_path($config, $id);
    } while (file_exists($path));

    $now = date(DATE_ATOM);
    $order = array_merge($clean, [
        'schema_version' => BOIS_SCHEMA_VERSION,
        'order_id' => $id,
        'public_token' => bin2hex(random_bytes(16)),
        'status' => 'received',
        'payment_status' => 'not_enabled',
        'supplier_status' => 'not_sent',
        'created_at' => $now,
        'updated_at' => $now,
        'version' => 1,
        'source' => bois_clean_string($meta['source'] ?? 'web', 40),
    ]);

    bois_atomic_write_json($path, $order);
    bois_audit($config, 'order_created', $id, [
        'status' => 'received',
        'team' => $order['team'],
        'order_period_id' => $order['order_period_id'],
        'product_id' => $order['product_id'],
    ]);
    return $order;
}

function bois_get_order(array $config, string $orderId): array
{
    $path = bois_order_path($config, $orderId);
    if (!is_file($path)) throw new OutOfBoundsException('Ordern finns inte.');
    return bois_read_json_file($path);
}

function bois_list_orders(array $config, array $filters=[]): array
{
    bois_prepare_storage($config);
    $orders=[];

    foreach (glob(rtrim($config['data_dir'], '/') . '/orders/*.json') ?: [] as $file) {
        try { $order=bois_read_json_file($file); } catch (Throwable) { continue; }

        if (($filters['team'] ?? '') !== '' && ($order['team'] ?? '') !== $filters['team']) continue;
        if (($filters['status'] ?? '') !== '' && ($order['status'] ?? '') !== $filters['status']) continue;
        if (($filters['order_period_id'] ?? '') !== '' && ($order['order_period_id'] ?? '') !== $filters['order_period_id']) continue;
        $orders[]=$order;
    }

    usort($orders, fn($a,$b)=>strcmp((string)($b['created_at']??''),(string)($a['created_at']??'')));
    return $orders;
}

function bois_update_status(array $config, string $orderId, string $status): array
{
    $allowed=['received','checked','ready_for_supplier','ordered','cancelled'];
    if (!in_array($status,$allowed,true)) throw new InvalidArgumentException('Ogiltig status.');

    $order=bois_get_order($config,$orderId);
    $from=(string)($order['status']??'');
    $order['status']=$status;
    date_default_timezone_set('Europe/Stockholm');
    $order['updated_at']=date(DATE_ATOM);
    $order['version']=(int)($order['version']??0)+1;

    bois_atomic_write_json(bois_order_path($config,$orderId),$order);
    bois_audit($config,'status_changed',$orderId,['from'=>$from,'to'=>$status]);
    return $order;
}

function bois_order_can_cancel(array $order): bool
{
    return in_array((string)($order['status'] ?? ''), ['received','checked'], true);
}

function bois_cancel_order(array $config, string $orderId, string $publicToken): array
{
    $order = bois_get_order($config, $orderId);

    if ($publicToken === '' || !hash_equals((string)$order['public_token'], $publicToken)) {
        throw new DomainException('Ej behörig.');
    }
    if (!bois_order_can_cancel($order)) {
        throw new InvalidArgumentException('Beställningen kan inte längre avbrytas i portalen.');
    }

    $from = (string)$order['status'];
    $order['status'] = 'cancelled';
    date_default_timezone_set('Europe/Stockholm');
    $order['updated_at'] = date(DATE_ATOM);
    $order['version'] = (int)($order['version'] ?? 0) + 1;

    bois_atomic_write_json(bois_order_path($config,$orderId),$order);
    bois_audit($config,'customer_cancelled',$orderId,['from'=>$from,'to'=>'cancelled']);
    return $order;
}

function bois_public_order(array $order): array
{
    return [
        'order_id'=>$order['order_id'],
        'product_id'=>$order['product_id'] ?? 'match-kit-knatte',
        'product_label'=>$order['product_label'] ?? 'Matchställ Knatte',
        'order_period_id'=>$order['order_period_id'] ?? '',
        'order_period_label'=>$order['order_period_label'] ?? '',
        'team'=>$order['team'],
        'player_name'=>$order['player_name'],
        'shirt_size'=>$order['shirt_size'],
        'shorts_size'=>$order['shorts_size'],
        'number'=>$order['number'],
        'name_print'=>$order['name_print'],
        'number_print'=>$order['number_print'],
        'total_ore'=>$order['total_ore'],
        'currency'=>$order['currency'],
        'status'=>$order['status'],
        'payment_status'=>$order['payment_status'],
        'cancelable'=>bois_order_can_cancel($order),
        'created_at'=>$order['created_at'],
        'updated_at'=>$order['updated_at'],
    ];
}

function bois_summary_rows(array $orders): array
{
    $groups=[];

    foreach ($orders as $order) {
        if (($order['status'] ?? '') === 'cancelled') continue;

        $key=implode('|',[
            (string)($order['team'] ?? ''),
            (string)($order['product_id'] ?? ''),
            (string)($order['shirt_size'] ?? ''),
            (string)($order['shorts_size'] ?? ''),
        ]);

        if (!isset($groups[$key])) {
            $groups[$key]=[
                'team'=>(string)($order['team'] ?? ''),
                'product_id'=>(string)($order['product_id'] ?? ''),
                'product_label'=>(string)($order['product_label'] ?? 'Matchställ'),
                'supplier_code'=>(string)($order['supplier_code'] ?? ''),
                'shirt_size'=>(string)($order['shirt_size'] ?? ''),
                'shorts_size'=>(string)($order['shorts_size'] ?? ''),
                'count'=>0,
            ];
        }
        $groups[$key]['count']++;
    }

    $rows=array_values($groups);
    usort($rows,function(array $a,array $b): int {
        return [$a['team'],$a['shirt_size'],$a['shorts_size']] <=> [$b['team'],$b['shirt_size'],$b['shorts_size']];
    });
    return $rows;
}

function bois_audit(array $config, string $event, string $orderId, array $context=[]): void
{
    $line=json_encode(['at'=>date(DATE_ATOM),'event'=>$event,'order_id'=>$orderId,'context'=>$context],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    @file_put_contents(rtrim($config['data_dir'],'/').'/audit.log',$line."\n",FILE_APPEND|LOCK_EX);
}

function bois_rate_limit(array $config, string $key): void
{
    bois_prepare_storage($config);
    $hash=hash('sha256',$key);
    $path=rtrim($config['data_dir'],'/').'/rate/'.$hash.'.json';
    $now=time();
    $window=$config['rate_limit_window'];
    $max=$config['rate_limit_max'];

    $fp=fopen($path,'c+');
    if($fp===false) return;

    try {
        if(!flock($fp,LOCK_EX)) return;
        $raw=stream_get_contents($fp);
        $times=[];
        if(is_string($raw)&&$raw!==''){
            $decoded=json_decode($raw,true);
            if(is_array($decoded)) $times=$decoded;
        }

        $times=array_values(array_filter($times,fn($time)=>is_int($time)&&$time>$now-$window));
        if(count($times)>=$max) throw new RuntimeException('För många försök. Vänta en stund och försök igen.');

        $times[]=$now;
        rewind($fp);
        ftruncate($fp,0);
        fwrite($fp,json_encode($times));
        fflush($fp);
        flock($fp,LOCK_UN);
    } finally {
        fclose($fp);
        @chmod($path,0600);
    }
}

function bois_require_admin(array $config, ?string $authorization): void
{
    $prefix='Bearer ';
    if(!is_string($authorization)||!str_starts_with($authorization,$prefix)) throw new DomainException('Ej behörig.');

    $token=substr($authorization,strlen($prefix));
    if($token===''||!hash_equals($config['admin_token'],$token)) throw new DomainException('Ej behörig.');
}
