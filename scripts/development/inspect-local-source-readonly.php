<?php
declare(strict_types=1);
$root=dirname(__DIR__,2);
$stage='D:/JAGO_LABA_STAGING/laravel-step1-4-20261101';
$configs=['market'=>require $root.'/config/database.php','konveksi'=>require $root.'/storage/private/konveksi/database.php'];
$result=['mode'=>'SELECT-only source comparison; no application bootstrap','schemas'=>[]];
foreach($configs as $kind=>$cfg) {
    $host=(string)($cfg['host']??'127.0.0.1'); $port=(int)($cfg['port']??3306);
    if(!in_array($host,['127.0.0.1','localhost'],true) || $port!==3306) { $result['schemas'][$kind]=['status'=>'not inspected; not local source :3306']; continue; }
    try {
        $pdo=new PDO("mysql:host=$host;port=$port;dbname=".$cfg['name'].';charset=utf8mb4',$cfg['user'],$cfg['pass'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_TIMEOUT=>3]);
        $pdo->exec('SET SESSION TRANSACTION READ ONLY'); $pdo->beginTransaction();
        $e=['status'=>'connected','schema'=>$cfg['name']];
        if($kind==='market') {
            $e['orphan_inventory']=(int)$pdo->query('SELECT COUNT(*) FROM inventory_stocks s LEFT JOIN product_variants v ON v.id=s.product_variant_id WHERE v.id IS NULL')->fetchColumn();
            $e['orphan_order_items']=(int)$pdo->query('SELECT COUNT(*) FROM marketplace_order_items i LEFT JOIN marketplace_orders o ON o.id=i.order_id WHERE o.id IS NULL')->fetchColumn();
        } else {
            $table=$pdo->query("SELECT table_name FROM system_table_map WHERE api_key='produk' OR LOWER(sheet_name)='produk' LIMIT 1")->fetchColumn();
            if(!is_string($table) || !preg_match('/^jl_data_[a-z0-9_]+$/',$table)) throw new RuntimeException('Unsafe source collection mapping');
            $missing=0; $products=0;
            foreach($pdo->query("SELECT _data_json FROM `$table`")->fetchAll(PDO::FETCH_COLUMN) as $json) {
                $r=json_decode($json,true,512,JSON_THROW_ON_ERROR); $products++; if(empty($r['id_stok_item'])) $missing++;
            }
            $e['products']=$products; $e['missing_stock_ids']=$missing;
        }
        $pdo->rollBack(); $result['schemas'][$kind]=$e;
    } catch(Throwable $e) { $result['schemas'][$kind]=['status'=>'inspection unavailable','error_class'=>get_class($e)]; }
}
file_put_contents($stage.'/evidence/current-source-comparison.json',json_encode($result,JSON_PRETTY_PRINT)."\n");
echo json_encode($result,JSON_PRETTY_PRINT)."\n";
