<?php
declare(strict_types=1);
$stage = 'D:/JAGO_LABA_STAGING/laravel-step1-4-20261101';
$cfg = require $stage.'/private/market.php';
$pdo = new PDO('mysql:host=127.0.0.1;port=3320;charset=utf8mb4', $cfg['user'], $cfg['pass'], [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$report = ['at'=>date(DATE_ATOM),'target'=>'local disposable staging :3320','mode'=>'SELECT-only','schemas'=>[]];
foreach (['jl_s14_market','jl_s14_konveksi'] as $schema) {
    $tables = $pdo->query("SELECT TABLE_NAME FROM information_schema.tables WHERE TABLE_SCHEMA='$schema' AND TABLE_TYPE='BASE TABLE' ORDER BY TABLE_NAME")->fetchAll(PDO::FETCH_COLUMN);
    $counts = []; $digests = [];
    foreach ($tables as $table) {
        $hashes=[];
        $q=$pdo->query("SELECT * FROM `$schema`.`$table`");
        while ($row=$q->fetch(PDO::FETCH_ASSOC)) $hashes[]=hash('sha256',json_encode($row, JSON_INVALID_UTF8_SUBSTITUTE|JSON_THROW_ON_ERROR));
        sort($hashes); $counts[$table]=count($hashes); $digests[$table]=hash('sha256',implode('', $hashes));
    }
    $fks = $pdo->query("SELECT CONSTRAINT_NAME,TABLE_NAME,COLUMN_NAME,REFERENCED_TABLE_NAME,REFERENCED_COLUMN_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA='$schema' AND REFERENCED_TABLE_NAME IS NOT NULL ORDER BY TABLE_NAME,CONSTRAINT_NAME,ORDINAL_POSITION")->fetchAll(PDO::FETCH_ASSOC);
    $groups=[];
    foreach($fks as $fk) $groups[$fk['TABLE_NAME'].':'.$fk['CONSTRAINT_NAME']][]=$fk;
    $violations=[];
    foreach ($groups as $name=>$columns) {
        $joins=[]; $nulls=[];
        foreach($columns as $col) {
            $joins[]='c.`'.$col['COLUMN_NAME'].'`=p.`'.$col['REFERENCED_COLUMN_NAME'].'`';
            $nulls[]='c.`'.$col['COLUMN_NAME'].'` IS NOT NULL';
        }
        $c=$columns[0];
        $sql="SELECT COUNT(*) FROM `$schema`.`{$c['TABLE_NAME']}` c LEFT JOIN `$schema`.`{$c['REFERENCED_TABLE_NAME']}` p ON ".implode(' AND ',$joins).' WHERE '.implode(' AND ',$nulls).' AND p.`'.$c['REFERENCED_COLUMN_NAME'].'` IS NULL';
        $n=(int)$pdo->query($sql)->fetchColumn();
        if($n) $violations[$name]=$n;
    }
    $columns=$pdo->query("SELECT TABLE_NAME,COLUMN_NAME,COLUMN_TYPE,IS_NULLABLE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='$schema' ORDER BY TABLE_NAME,ORDINAL_POSITION")->fetchAll(PDO::FETCH_ASSOC);
    $report['schemas'][$schema]=['table_count'=>count($tables),'row_counts'=>$counts,'foreign_keys_checked'=>count($groups),'fk_violations'=>$violations];
    file_put_contents($stage.'/private/'.$schema.'-fingerprints.json',json_encode(['counts'=>$counts,'digests'=>$digests,'columns'=>$columns],JSON_PRETTY_PRINT)."\n");
}
$pdo->exec('USE jl_s14_market');
$report['orphan_summary']=$pdo->query('SELECT COUNT(*) AS rows_affected, COALESCE(SUM(s.stock_qty),0) AS units, COALESCE(SUM(s.stock_qty*s.average_hpp),0) AS implied_value FROM inventory_stocks s LEFT JOIN product_variants v ON v.id=s.product_variant_id WHERE v.id IS NULL')->fetch(PDO::FETCH_ASSOC);
$report['orphan_order_items_summary']=$pdo->query('SELECT COUNT(*) AS rows_affected, COALESCE(SUM(i.qty),0) AS quantity, COALESCE(SUM(i.subtotal_price),0) AS subtotal_price, COALESCE(SUM(i.subtotal_cogs),0) AS subtotal_cogs FROM marketplace_order_items i LEFT JOIN marketplace_orders o ON o.id=i.order_id WHERE o.id IS NULL')->fetch(PDO::FETCH_ASSOC);
$orphanItems=$pdo->query('SELECT i.id,i.order_id,i.sku,i.qty,i.subtotal_price,i.subtotal_cogs FROM marketplace_order_items i LEFT JOIN marketplace_orders o ON o.id=i.order_id WHERE o.id IS NULL ORDER BY i.id')->fetchAll(PDO::FETCH_ASSOC);
$f=fopen($stage.'/private/orphan-order-items-owner-decisions.csv','w');
fputcsv($f,['item_id','missing_order_id','sku','qty','subtotal_price','subtotal_cogs','disposition','authoritative_source','ledger_reference','approved_by','approved_at'],',','"','');
foreach($orphanItems as $row) fputcsv($f,array_merge(array_values($row),array_fill(0,5,'')),',','"','');
fclose($f);
$orphans=$pdo->query('SELECT s.id,s.product_variant_id,s.stock_qty,s.average_hpp FROM inventory_stocks s LEFT JOIN product_variants v ON v.id=s.product_variant_id WHERE v.id IS NULL ORDER BY s.id')->fetchAll(PDO::FETCH_ASSOC);
$evidenceRows=[];
foreach(['jl_s14_market','jl_s14_konveksi'] as $sourceSchema) {
    $q=$pdo->query("SELECT table_name FROM `$sourceSchema`.system_table_map WHERE api_key='master_stok' OR LOWER(sheet_name)='master_stok'");
    foreach($q->fetchAll(PDO::FETCH_COLUMN) as $table) {
        if(!preg_match('/^jl_data_[a-z0-9_]+$/',$table)) throw new RuntimeException('Unsafe collection table');
        foreach($pdo->query("SELECT _data_json FROM `$sourceSchema`.`$table`")->fetchAll(PDO::FETCH_COLUMN) as $json) {
            $r=json_decode($json,true,512,JSON_THROW_ON_ERROR); $r['_source']=$sourceSchema.'.'.$table; $evidenceRows[]=$r;
        }
    }
}
$candidateReport=[];
foreach($orphans as $orphan) {
    $matches=[];
    foreach($evidenceRows as $r) if((string)($r['id_stok_item']??'')===$orphan['product_variant_id']) {
        $sku=trim((string)($r['kode_sku']??''));
        $q=$pdo->prepare('SELECT id,product_id,sku FROM product_variants WHERE BINARY sku=BINARY ?'); $q->execute([$sku]);
        $variants=$q->fetchAll(PDO::FETCH_ASSOC);
        foreach($variants as $variant) {
            $q=$pdo->prepare('SELECT id,stock_qty,average_hpp FROM inventory_stocks WHERE product_variant_id=? AND warehouse_id=(SELECT warehouse_id FROM inventory_stocks WHERE id=?)');
            $q->execute([$variant['id'],$orphan['id']]);
            $matches[]=['source'=>$r['_source'],'sku'=>$sku,'canonical_candidate'=>$variant['id'],'existing_target_stocks'=>$q->fetchAll(PDO::FETCH_ASSOC),
                'source_qty'=>$r['stok_akhir']??$r['stok']??null,'source_hpp'=>$r['hpp_rata_rata']??null];
        }
    }
    $candidateReport[]=['stock_id'=>$orphan['id'],'missing_variant_id'=>$orphan['product_variant_id'],'candidates'=>$matches,'approved'=>false];
}
file_put_contents($stage.'/private/orphan-source-candidates.json',json_encode($candidateReport,JSON_PRETTY_PRINT)."\n");
$report['orphan_mapping_investigation']=['rows_with_source_candidates'=>count(array_filter($candidateReport,fn($r)=>count($r['candidates'])>0)),
    'rows_with_target_stock_collision'=>count(array_filter($candidateReport,fn($r)=>count(array_filter($r['candidates'],fn($c)=>count($c['existing_target_stocks'])>0))>0)),
    'candidate_file'=>'private/orphan-source-candidates.json','mapping_approved'=>false];
$f=fopen($stage.'/private/orphan-owner-decisions.csv','w');
fputcsv($f,['stock_id','missing_variant_id','stock_qty','average_hpp','canonical_variant_id','disposition','authoritative_source','ledger_reference','approved_by','approved_at'],',','"','');
foreach($orphans as $row) fputcsv($f,array_merge(array_values($row),array_fill(0,6,'')),',','"','');
fclose($f);
$report['write_rejected']=false;
try { $pdo->exec('CREATE TABLE jl_s14_market.must_not_create (id INT)'); } catch (PDOException $e) { $report['write_rejected']=in_array($e->errorInfo[1]??0,[1142,1044],true); }
if(!$report['write_rejected']) throw new RuntimeException('SELECT-only account write boundary failed');
$report['read_grants']=array_map(fn($s)=>preg_replace('/ IDENTIFIED BY.*$/i',' [credential omitted]',$s),$pdo->query('SHOW GRANTS FOR CURRENT_USER')->fetchAll(PDO::FETCH_COLUMN));
$report['data_repair_executed']=false;
$report['integrity_pass']=array_sum(array_map(fn($s)=>array_sum($s['fk_violations']),$report['schemas']))===0;
file_put_contents($stage.'/evidence/data-audit.json',json_encode($report,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES)."\n");
echo json_encode(['tables'=>array_map(fn($s)=>$s['table_count'],$report['schemas']),'fk_violations'=>array_map(fn($s)=>$s['fk_violations'],$report['schemas']),'orphans'=>$report['orphan_summary'],'write_rejected'=>$report['write_rejected']],JSON_PRETTY_PRINT)."\n";
