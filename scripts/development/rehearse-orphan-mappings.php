<?php
declare(strict_types=1);
// Technical proposal experiment: always rolls back. Never chooses stock/HPP balances.
$stage='D:/JAGO_LABA_STAGING/laravel-step1-4-20261101';
$cfg=require $stage.'/private/admin.php';
$schema='jl_s14_schema_rehearsal_v2';
$pdo=new PDO("mysql:host=127.0.0.1;port=3320;dbname=$schema;charset=utf8mb4",$cfg['user'],$cfg['pass'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
if((int)$pdo->query('SELECT @@port')->fetchColumn()!==3320 || $pdo->query('SELECT DATABASE()')->fetchColumn()!==$schema) throw new RuntimeException('Diagnostic clone guard failed');
$fingerprint=function() use($pdo):string {
    $rows=[]; foreach($pdo->query('SELECT * FROM inventory_stocks')->fetchAll(PDO::FETCH_ASSOC) as $row) $rows[]=hash('sha256',json_encode($row,JSON_THROW_ON_ERROR));
    sort($rows); return hash('sha256',implode('',$rows));
};
$orphanCount=fn()=>(int)$pdo->query('SELECT COUNT(*) FROM inventory_stocks s LEFT JOIN product_variants v ON v.id=s.product_variant_id WHERE v.id IS NULL')->fetchColumn();
$totals=fn()=>$pdo->query('SELECT SUM(stock_qty) AS units,SUM(stock_qty*average_hpp) AS valuation FROM inventory_stocks')->fetch(PDO::FETCH_ASSOC);
$before=$fingerprint(); $beforeTotals=$totals(); $beforeOrphans=$orphanCount();
$candidates=json_decode(file_get_contents($stage.'/private/orphan-source-candidates.json'),true,512,JSON_THROW_ON_ERROR);
$e=['scope'=>'rolled-back proposal on diagnostic clone only','target'=>$schema,'before_orphan_stock_rows'=>$beforeOrphans,'applied_proposals'=>[],'held'=>[],'business_mapping_approved'=>false];
$pdo->beginTransaction();
try {
    foreach($candidates as $candidate) {
        $ids=array_values(array_unique(array_column($candidate['candidates'],'canonical_candidate')));
        if(count($ids)!==1) { $e['held'][]=['stock_id'=>$candidate['stock_id'],'reason'=>'ambiguous source identity']; continue; }
        $q=$pdo->prepare('SELECT id,warehouse_id,product_variant_id,stock_qty,average_hpp FROM inventory_stocks WHERE id=? FOR UPDATE'); $q->execute([$candidate['stock_id']]); $row=$q->fetch(PDO::FETCH_ASSOC);
        if(!$row || $row['product_variant_id']!==$candidate['missing_variant_id']) throw new RuntimeException('Proposal precondition drift');
        $q=$pdo->prepare('SELECT COUNT(*) FROM inventory_stocks WHERE warehouse_id=? AND product_variant_id=?'); $q->execute([$row['warehouse_id'],$ids[0]]);
        if((int)$q->fetchColumn()>0) { $e['held'][]=['stock_id'=>$row['id'],'reason'=>'target warehouse/variant stock already exists; owner quantity/HPP decision required']; continue; }
        $q=$pdo->prepare('UPDATE inventory_stocks SET product_variant_id=? WHERE id=? AND product_variant_id=?'); $q->execute([$ids[0],$row['id'],$row['product_variant_id']]);
        if($q->rowCount()!==1) throw new RuntimeException('Proposal update count mismatch');
        $e['applied_proposals'][]=['stock_id'=>$row['id'],'from'=>$row['product_variant_id'],'to'=>$ids[0]];
    }
    $e['during_transaction_orphan_stock_rows']=$orphanCount();
    $e['stock_quantity_and_valuation_preserved']=$totals()===$beforeTotals;
    if(!$e['stock_quantity_and_valuation_preserved']) throw new RuntimeException('Proposal changed inventory totals');
} finally { if($pdo->inTransaction()) $pdo->rollBack(); }
$e['rolled_back']=true;
$e['after_rollback_orphan_stock_rows']=$orphanCount();
$e['after_rollback_all_stock_data_matches']=$fingerprint()===$before;
if(!$e['after_rollback_all_stock_data_matches']) throw new RuntimeException('Proposal rollback failed');
file_put_contents($stage.'/evidence/orphan-mapping-rehearsal.json',json_encode($e,JSON_PRETTY_PRINT)."\n");
echo json_encode(['proposal_updates_tested'=>count($e['applied_proposals']),'held'=>$e['held'],'orphan_rows_during_test'=>$e['during_transaction_orphan_stock_rows'],'rolled_back'=>$e['rolled_back'],'original_stock_data_restored'=>$e['after_rollback_all_stock_data_matches']],JSON_PRETTY_PRINT)."\n";
