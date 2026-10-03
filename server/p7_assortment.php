<?php
declare(strict_types=1);
require_once __DIR__ . "/security.php";

require_once __DIR__ . '/p3_db.php';

function bois_p7_apply_schema(PDO $pdo): void
{
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS bois_p7_assortment (
            product_id BIGINT UNSIGNED PRIMARY KEY,
            supplier_id BIGINT UNSIGNED NULL,
            supplier_candidate VARCHAR(160) NULL,
            supplier_status VARCHAR(12) NOT NULL DEFAULT 'TBD',
            supplier_sku VARCHAR(100) NULL,
            sku_status VARCHAR(12) NOT NULL DEFAULT 'TBD',
            purchase_price_ore INT UNSIGNED NULL,
            decoration_cost_ore INT UNSIGNED NULL,
            shipping_handling_ore INT UNSIGNED NULL,
            sale_price_ore INT UNSIGNED NULL,
            sale_price_ex_vat_ore INT UNSIGNED NULL,
            price_status VARCHAR(12) NOT NULL DEFAULT 'TBD',
            moq INT UNSIGNED NULL,
            lead_time_days INT UNSIGNED NULL,
            stock_strategy VARCHAR(40) NOT NULL DEFAULT 'UNDECIDED',
            return_class VARCHAR(40) NOT NULL DEFAULT 'REVIEW_REQUIRED',
            verification_status VARCHAR(12) NOT NULL DEFAULT 'TBD',
            launch_date DATE NOT NULL DEFAULT '2027-01-01',
            approved TINYINT(1) NOT NULL DEFAULT 0,
            preview_group VARCHAR(80) NOT NULL,
            preview_copy TEXT NULL,
            image_url VARCHAR(500) NULL,
            source_json JSON NOT NULL,
            blockers_json JSON NOT NULL,
            verified_at DATE NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_bois_p7_launch (approved,launch_date,verification_status),
            CONSTRAINT fk_bois_p7_product FOREIGN KEY (product_id) REFERENCES bois_products(id),
            CONSTRAINT fk_bois_p7_supplier FOREIGN KEY (supplier_id) REFERENCES bois_suppliers(id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS bois_p7_variants (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            product_id BIGINT UNSIGNED NOT NULL,
            variant_key VARCHAR(100) NOT NULL,
            supplier_sku VARCHAR(100) NULL,
            size_label VARCHAR(40) NULL,
            color_label VARCHAR(80) NULL,
            sale_price_ore INT UNSIGNED NULL,
            verification_status VARCHAR(12) NOT NULL DEFAULT 'TBD',
            source_json JSON NOT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_bois_p7_variant (product_id,variant_key),
            INDEX idx_bois_p7_variant_sku (supplier_sku),
            CONSTRAINT fk_bois_p7_variant_product FOREIGN KEY (product_id) REFERENCES bois_products(id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );
    $pdo->prepare('INSERT IGNORE INTO bois_schema_migrations(version) VALUES(?)')
        ->execute(['20260927_p7_assortment_v1']);
}

function bois_p7_seed_assortment(PDO $pdo,?string $path=null): void
{
    $path=$path ?: (getenv('BOIS_P7_ASSORTMENT_PATH') ?: dirname(__DIR__).'/data/p7-assortment.json');
    if(!is_file($path)) throw new RuntimeException('P7 assortment seed saknas.');
    $data=json_decode((string)file_get_contents($path),true,64,JSON_THROW_ON_ERROR);
    if(!is_array($data['products']??null)) throw new RuntimeException('Ogiltigt P7-underlag.');

    $pdo->beginTransaction();
    try{
        $product=$pdo->prepare(
            "INSERT INTO bois_products(product_key,name,category,description,price_ore,fulfillment_type,is_public,is_orderable,active_from,metadata_json)
             VALUES(?,?,'supporter',?,NULL,'DIRECT_SUPPLIER',0,0,'2027-01-01 00:00:00',?)
             ON DUPLICATE KEY UPDATE id=id"
        );
        $find=$pdo->prepare('SELECT id FROM bois_products WHERE product_key=?');
        $upsert=$pdo->prepare(
            "INSERT INTO bois_p7_assortment(
                product_id,supplier_candidate,supplier_status,supplier_sku,sku_status,
                purchase_price_ore,decoration_cost_ore,shipping_handling_ore,sale_price_ore,
                moq,lead_time_days,stock_strategy,return_class,price_status,verification_status,launch_date,
                approved,preview_group,preview_copy,source_json,blockers_json)
             VALUES(?,?,?,NULL,'TBD',NULL,NULL,NULL,?,?,?,?,?,'ESTIMATE','TBD','2027-01-01',0,?,?,?,?)
             ON DUPLICATE KEY UPDATE product_id=product_id"
        );
        $variant=$pdo->prepare(
            "INSERT INTO bois_p7_variants(product_id,variant_key,size_label,color_label,sale_price_ore,verification_status,source_json)
             VALUES(?,?,?,?,?,'ESTIMATE',?) ON DUPLICATE KEY UPDATE id=id"
        );
        foreach($data['products'] as $item){
            $key=(string)$item['product_key'];
            $product->execute([$key,(string)$item['name'],(string)$item['copy'],json_encode(['p7_preview'=>true],JSON_THROW_ON_ERROR)]);
            $find->execute([$key]);
            $id=(int)$find->fetchColumn();
            if($id<1) throw new RuntimeException('P7 produkt saknas: '.$key);
            $upsert->execute([
                $id,(string)($item['supplier']['candidate']??''),(string)$item['supplier']['status'],
                $item['recommended_sale_price_ore']['value'],$item['moq']['value'],$item['lead_time_days']['value'],
                (string)$item['stock_strategy']['value'],(string)$item['return_class']['value'],
                (string)$item['group'],(string)$item['copy'],
                json_encode(['sources'=>$item['supplier']['source_ids'],'checked_at'=>$data['verification_date']],JSON_THROW_ON_ERROR),
                json_encode($item['blockers'],JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR)
            ]);
            foreach($item['variants'] as $v){
                $variantKey=strtolower(preg_replace('/[^a-z0-9]+/','-',str_replace('ö','o',$key.'-'.$v['size'].'-'.$v['color'])) ?? '');
                $variant->execute([$id,$variantKey,(string)$v['size'],(string)$v['color'],
                    $item['recommended_sale_price_ore']['value'],json_encode(['status'=>'ESTIMATE'],JSON_THROW_ON_ERROR)]);
            }
        }
        $pdo->commit();
    }catch(Throwable $e){
        if($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}

function bois_p7_admin_assortment(PDO $pdo): array
{
    $rows=$pdo->query(
        "SELECT p.product_key,p.name,p.category,p.fulfillment_type,p.is_public,p.is_orderable,
                a.supplier_candidate,a.supplier_status,a.supplier_sku,a.sku_status,a.purchase_price_ore,
                a.decoration_cost_ore,a.shipping_handling_ore,a.sale_price_ore,a.sale_price_ex_vat_ore,
                a.moq,a.lead_time_days,a.stock_strategy,a.return_class,a.verification_status,
                a.launch_date,a.approved,a.preview_group,a.preview_copy,a.image_url,a.source_json,a.blockers_json,
                a.verified_at,v.variant_key,v.supplier_sku variant_supplier_sku,v.size_label,v.color_label,
                v.sale_price_ore variant_sale_price_ore,v.verification_status variant_verification_status
         FROM bois_p7_assortment a JOIN bois_products p ON p.id=a.product_id
         LEFT JOIN bois_p7_variants v ON v.product_id=p.id ORDER BY a.preview_group,p.id,v.id"
    )->fetchAll();
    $products=[];
    foreach($rows as $row){
        $variantRow=$row;
        $key=(string)$row['product_key'];
        if(!isset($products[$key])){
            $costs=[$row['purchase_price_ore'],$row['decoration_cost_ore'],$row['shipping_handling_ore'],$row['sale_price_ex_vat_ore']];
            $margin=in_array(null,$costs,true)?null:(int)$row['sale_price_ex_vat_ore']-(int)$row['purchase_price_ore']-(int)$row['decoration_cost_ore']-(int)$row['shipping_handling_ore'];
            $row['margin_ore']=$margin;
            $row['margin_pct']=$margin===null || (int)$row['sale_price_ex_vat_ore']===0 ? null : round(100*$margin/(int)$row['sale_price_ex_vat_ore'],1);
            $row['sources']=json_decode((string)$row['source_json'],true) ?: [];
            $row['blockers']=json_decode((string)$row['blockers_json'],true) ?: [];
            unset($row['source_json'],$row['blockers_json'],$row['variant_key'],$row['variant_supplier_sku'],$row['size_label'],$row['color_label'],$row['variant_sale_price_ore'],$row['variant_verification_status']);
            $row['variants']=[];
            $products[$key]=$row;
        }
        if($variantRow['variant_key']!==null){
            $products[$key]['variants'][]=[
                'variant_key'=>$variantRow['variant_key'],'supplier_sku'=>$variantRow['variant_supplier_sku'],
                'size'=>$variantRow['size_label'],'color'=>$variantRow['color_label'],
                'sale_price_ore'=>$variantRow['variant_sale_price_ore'],
                'verification_status'=>$variantRow['variant_verification_status'],
            ];
        }
    }
    return array_values($products);
}

function bois_p7_restricted_product(string $productKey): bool
{
    return !in_array($productKey,['membership','nordic-gym','match-kit'],true);
}

function bois_p7_launch_allowed(PDO $pdo,string $productKey,?DateTimeImmutable $at=null): bool
{
    if(!bois_p7_restricted_product($productKey)) return true;
    $at=($at ?? new DateTimeImmutable('now',new DateTimeZone('Europe/Stockholm')))
        ->setTimezone(new DateTimeZone('Europe/Stockholm'));
    if($at->format('Y-m-d')<'2027-01-01') return false;

    $stmt=$pdo->prepare(
        "SELECT a.*,p.is_public,p.is_orderable,p.price_ore
         FROM bois_p7_assortment a JOIN bois_products p ON p.id=a.product_id
         WHERE p.product_key=? LIMIT 1"
    );
    $stmt->execute([$productKey]);
    $a=$stmt->fetch();
    if(!$a || !$a['approved'] || !$a['is_public'] || !$a['is_orderable'] ||
       $at->format('Y-m-d')<(string)$a['launch_date'] ||
       $a['verification_status']!=='VERIFIED' || $a['supplier_status']!=='VERIFIED' ||
       $a['sku_status']!=='VERIFIED' || $a['price_status']!=='VERIFIED' ||
       $a['supplier_id']===null || $a['supplier_sku']===null ||
       $a['purchase_price_ore']===null || $a['decoration_cost_ore']===null ||
       $a['shipping_handling_ore']===null || $a['sale_price_ore']===null ||
       $a['sale_price_ex_vat_ore']===null || $a['moq']===null || $a['lead_time_days']===null ||
       $a['price_ore']===null || (int)$a['price_ore']!==(int)$a['sale_price_ore'] ||
       count(json_decode((string)$a['blockers_json'],true) ?: [])>0){
        return false;
    }

    $variants=$pdo->prepare(
        "SELECT COUNT(*) total,
                SUM(CASE WHEN pv.supplier_sku IS NULL OR pv.verification_status<>'VERIFIED' OR
                              v.id IS NULL OR v.price_ore IS NULL OR v.active<>1
                         THEN 1 ELSE 0 END) invalid_count
         FROM bois_p7_variants pv
         LEFT JOIN bois_variants v ON v.product_id=pv.product_id AND v.sku=pv.supplier_sku
         WHERE pv.product_id=?"
    );
    $variants->execute([(int)$a['product_id']]);
    $v=$variants->fetch();
    return (int)$v['total']>0 && (int)$v['invalid_count']===0;
}
