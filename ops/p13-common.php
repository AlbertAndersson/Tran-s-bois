<?php
declare(strict_types=1);
require_once __DIR__.'/p12-common.php';

/** Read-only logical snapshot. Never emit row values or credentials. */
function bois_p13_snapshot(PDO $pdo): array
{
    if ($pdo->inTransaction()) throw new RuntimeException('Separate readonly connection required.');
    $pdo->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
    $pdo->exec('START TRANSACTION WITH CONSISTENT SNAPSHOT, READ ONLY');
    try {
        $tables=$pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);sort($tables);
        if ($tables!==bois_p12_tables()) throw new RuntimeException('Unexpected table manifest.');
        $ledger=$pdo->query('SELECT version FROM bois_schema_migrations ORDER BY version')->fetchAll(PDO::FETCH_COLUMN);
        $expected=bois_p12_versions();sort($expected);
        if ($ledger!==$expected) throw new RuntimeException('Unexpected migration ledger.');
        $result=['format'=>1,'ledger'=>$ledger,'tables'=>[]];
        foreach ($tables as $table) {
            $ddl=$pdo->query('SHOW CREATE TABLE `'.$table.'`')->fetch(PDO::FETCH_NUM)[1];
            $rows=$pdo->query('SELECT * FROM `'.$table.'`')->fetchAll(PDO::FETCH_ASSOC);
            $encoded=array_map(fn($row)=>json_encode($row,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),$rows);
            sort($encoded,SORT_STRING);
            $result['tables'][$table]=['schema_sha256'=>hash('sha256',$ddl),'rows'=>count($rows),
                'data_sha256'=>hash('sha256',json_encode($encoded,JSON_THROW_ON_ERROR))];
        }
        // The current BoIS manifest has single-column FKs. Fail if that changes.
        $fk=$pdo->query("SELECT CONSTRAINT_NAME,TABLE_NAME,COLUMN_NAME,REFERENCED_TABLE_NAME,REFERENCED_COLUMN_NAME
            FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=DATABASE() AND REFERENCED_TABLE_NAME IS NOT NULL
            ORDER BY TABLE_NAME,CONSTRAINT_NAME,ORDINAL_POSITION")->fetchAll();
        $seen=[];
        foreach ($fk as $edge) {
            $key=$edge['TABLE_NAME'].'.'.$edge['CONSTRAINT_NAME'];
            if(isset($seen[$key])) throw new RuntimeException('Composite FK requires updated verifier.');
            $seen[$key]=true;
            foreach (['TABLE_NAME','COLUMN_NAME','REFERENCED_TABLE_NAME','REFERENCED_COLUMN_NAME'] as $name)
                if(!preg_match('/^[a-zA-Z0-9_]+$/D',$edge[$name])) throw new RuntimeException('Unsafe identifier.');
            $t=$edge['TABLE_NAME'];$c=$edge['COLUMN_NAME'];$rt=$edge['REFERENCED_TABLE_NAME'];$rc=$edge['REFERENCED_COLUMN_NAME'];
            if((int)$pdo->query("SELECT COUNT(*) FROM `$t` c LEFT JOIN `$rt` p ON c.`$c`=p.`$rc` WHERE c.`$c` IS NOT NULL AND p.`$rc` IS NULL")->fetchColumn()!==0)
                throw new RuntimeException('Orphaned relation.');
        }
        $result['foreign_keys_checked']=count($seen);
        $pdo->commit();return $result;
    } catch(Throwable $e) {
        if($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}

function bois_p13_equal(array $source,array $restored): void
{
    if($source!==$restored) {
        $differences=[];
        foreach($source['tables']??[] as $table=>$checks)
            foreach($checks as $key=>$value)
                if(($restored['tables'][$table][$key]??null)!==$value) $differences[]=$table.'.'.$key;
        throw new RuntimeException('Restored schema, ledger or data differs: '.implode(',',$differences));
    }
}
