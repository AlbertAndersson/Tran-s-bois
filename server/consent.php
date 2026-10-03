<?php
declare(strict_types=1);
require_once __DIR__ . "/security.php";

// The opaque, HttpOnly cookie is a random capability; only its hash is stored.
// A revoked or expired capability can never be reactivated by replaying it.
const BOIS_CONSENT_POLICY = 'statistics-v1';
const BOIS_CONSENT_COOKIE = 'boisConsent';

function bois_consent_days(array $config): int
{
    $days=$config['consent_validity_days']??180;
    if(($config['mode']??'')==='production'&&(!is_int($config['consent_validity_days']??null)||$days<1||$days>365))throw new RuntimeException('Production consent validity is undecided.');
    return is_int($days)&&$days>=1&&$days<=365 ? $days : 180;
}

function bois_consent_cookie_path(array $config): string
{
    $path=$config['consent_cookie_path']??'/bois-shop-p3/';
    if(($config['mode']??'')==='production'&&!is_string($config['consent_cookie_path']??null))throw new RuntimeException('Production consent cookie scope is undecided.');
    if(!is_string($path)||!preg_match('#^/(?:[A-Za-z0-9_-]+/)*$#D',$path))throw new RuntimeException('Invalid consent cookie scope.');
    return $path;
}

function bois_consent_schema(PDO $pdo): void
{
    $pdo->exec("CREATE TABLE IF NOT EXISTS bois_consent_choices (
        token_hash CHAR(64) PRIMARY KEY,
        policy_version VARCHAR(40) NOT NULL,
        statistics TINYINT(1) NOT NULL,
        decided_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        expires_at DATETIME NOT NULL,
        revoked_at DATETIME NULL,
        INDEX idx_bois_consent_expiry (expires_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}

function bois_consent_token(): string
{
    $token=$_COOKIE[BOIS_CONSENT_COOKIE]??'';
    return is_string($token)&&preg_match('/^[a-f0-9]{64}$/D',$token) ? $token : '';
}

function bois_consent_choice(PDO $pdo): array
{
    $token=bois_consent_token();
    if($token==='') return ['decided'=>false,'statistics'=>false];
    $stmt=$pdo->prepare('SELECT policy_version,statistics,expires_at,revoked_at FROM bois_consent_choices WHERE token_hash=?');
    $stmt->execute([hash('sha256',$token)]);
    $row=$stmt->fetch(PDO::FETCH_ASSOC);
    if(!$row || $row['policy_version']!==BOIS_CONSENT_POLICY || $row['revoked_at']!==null || strtotime($row['expires_at'].' UTC')<=time()){
        return ['decided'=>false,'statistics'=>false];
    }
    return ['decided'=>true,'statistics'=>(int)$row['statistics']===1];
}

function bois_consent_statistics_allowed(PDO $pdo): bool
{
    try{return bois_consent_choice($pdo)['statistics']===true;}
    catch(Throwable){return false;}
}

function bois_consent_save(PDO $pdo,array $config,bool $statistics): array
{
    $days=bois_consent_days($config);$cookiePath=bois_consent_cookie_path($config);
    $old=bois_consent_token();
    $pdo->beginTransaction();
    try{
        if($old!==''){
            $stmt=$pdo->prepare('UPDATE bois_consent_choices SET revoked_at=UTC_TIMESTAMP() WHERE token_hash=? AND revoked_at IS NULL');
            $stmt->execute([hash('sha256',$old)]);
        }
        $token=bin2hex(random_bytes(32));
        $stmt=$pdo->prepare('INSERT INTO bois_consent_choices(token_hash,policy_version,statistics,expires_at) VALUES(?,?,?,DATE_ADD(UTC_TIMESTAMP(), INTERVAL ? DAY))');
        $stmt->execute([hash('sha256',$token),BOIS_CONSENT_POLICY,$statistics?1:0,$days]);
        $pdo->commit();
    }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
    setcookie(BOIS_CONSENT_COOKIE,$token,[
        'expires'=>time()+$days*86400,'path'=>$cookiePath,
        'secure'=>!in_array((string)($config['mode']??''),['test'],true),
        'httponly'=>true,'samesite'=>'Lax'
    ]);
    return ['decided'=>true,'statistics'=>$statistics,'policy_version'=>BOIS_CONSENT_POLICY,'validity_days'=>$days];
}
