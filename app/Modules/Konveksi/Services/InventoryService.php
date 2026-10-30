<?php
final class InventoryService {
    private PDO $pdo; private CollectionStore $store;
    public function __construct(PDO $pdo,CollectionStore $store){$this->pdo=$pdo;$this->store=$store;}
    private function id(string $p): string{return $p.'-'.date('Ymd').'-'.strtoupper(substr(bin2hex(random_bytes(5)),0,8));}
    private function now(): string{return date(DATE_ATOM);}
    private static function sku(array $r): string{return (string)($r['kode_sku']??$r['sku_varian']??$r['kode']??'');}
    private static function pid(array $r): string{return (string)($r['id_produk']??$r['id']??'');}
    private static function sid(array $r): string{return (string)($r['id_stok_item']??'');}
    private function findIndex(array $arr,array $p): int {
        $sid=(string)($p['id_stok_item']??'');$sku=(string)($p['kode_sku']??'');$pid=(string)($p['id_produk']??'');
        foreach($arr as $i=>$r){if($sid!==''&&self::sid($r)===$sid)return $i;if($sku!==''&&strcasecmp(self::sku($r),$sku)===0)return $i;if($pid!==''&&self::pid($r)===$pid)return $i;}return -1;
    }
    public function migrateIds(): array {
        $produk=$this->store->get('produk');$master=$this->store->get('master_stok');$changed=0;$map=[];
        foreach($produk as &$p){if(empty($p['id_stok_item'])){$p['id_stok_item']='STK-'.strtoupper(substr(bin2hex(random_bytes(6)),0,10));$changed++;}$map[strtoupper(self::sku($p))]=$p['id_stok_item'];}
        unset($p);
        foreach($master as &$m){if(empty($m['id_stok_item'])){$key=strtoupper(self::sku($m));$m['id_stok_item']=$map[$key]??('STK-'.strtoupper(substr(bin2hex(random_bytes(6)),0,10)));$changed++;}}
        unset($m);
        $this->store->putMany(['produk'=>$produk,'master_stok'=>$master]);AuditService::log($this->pdo,'MIGRATION_ID_STOCK_ITEM','Inventory',null,null,null,['changed'=>$changed]);return ['status'=>'success','code'=>'INVENTORY_ID_MIGRATED','updated'=>$changed,'message'=>'Migrasi id_stok_item selesai.'];
    }
    public function audit(): array {
        $produk=$this->store->get('produk');$master=$this->store->get('master_stok');$mut=$this->store->get('mutasi_stok');$missing=[];$dup=[];$negative=[];$mismatch=[];$seen=[];
        foreach($produk as $p){$sid=self::sid($p);if($sid==='')$missing[]=['type'=>'PRODUK','kode_sku'=>self::sku($p),'id_produk'=>self::pid($p)];else{if(isset($seen[$sid]))$dup[]=$sid;$seen[$sid]=1;}}
        foreach($master as $m){$stock=(float)($m['stok_akhir']??$m['stok']??0);if($stock<0)$negative[]=['kode_sku'=>self::sku($m),'stok'=>$stock];$pi=$this->findIndex($produk,['id_stok_item'=>self::sid($m),'kode_sku'=>self::sku($m),'id_produk'=>self::pid($m)]);if($pi>=0){$ps=(float)($produk[$pi]['stok']??0);if(abs($ps-$stock)>0.00001)$mismatch[]=['kode_sku'=>self::sku($m),'produk_stok'=>$ps,'master_stok'=>$stock];}}
        $status=(!$missing&&!$dup&&!$negative&&!$mismatch)?'PASS':'WARNING';$report=['status'=>$status,'timestamp'=>$this->now(),'total_produk'=>count($produk),'total_master_stok'=>count($master),'total_mutasi'=>count($mut),'missing_id_stok_item'=>$missing,'duplicate_id_stok_item'=>array_values(array_unique($dup)),'negative_stock'=>$negative,'produk_master_mismatch'=>$mismatch,'summary'=>['missing'=>count($missing),'duplicate'=>count(array_unique($dup)),'negative'=>count($negative),'mismatch'=>count($mismatch)]];AuditService::log($this->pdo,'AUDIT_INVENTORY','Inventory',null,null,null,$report,$status==='PASS'?'SUCCESS':'WARNING');return $report;
    }
    public function postMovement(array $p): array {
        $qtyIn=max(0,(float)($p['qty_masuk']??0));$qtyOut=max(0,(float)($p['qty_keluar']??0));if($qtyIn<=0&&$qtyOut<=0)throw new RuntimeException('Qty mutasi harus lebih dari 0.');
        $key=trim((string)($p['idempotency_key']??''));if($key!==''){$st=$this->pdo->prepare('SELECT response_json FROM idempotency_keys WHERE idempotency_key=? LIMIT 1');$st->execute([$key]);$prev=$st->fetchColumn();if($prev)return json_decode($prev,true)?:['status'=>'success','code'=>'DUPLICATE_IGNORED'];}
        $lockName='jago_laba_inventory';$lk=$this->pdo->prepare('SELECT GET_LOCK(?,10)');$lk->execute([$lockName]);if((int)$lk->fetchColumn()!==1)throw new RuntimeException('Inventory sedang diproses transaksi lain.');
        try{
            $produk=$this->store->get('produk');$master=$this->store->get('master_stok');$mut=$this->store->get('mutasi_stok');$mi=$this->findIndex($master,$p);$pi=$this->findIndex($produk,$p);
            if($mi<0){$sku=(string)($p['kode_sku']??($pi>=0?self::sku($produk[$pi]):''));$sid=(string)($p['id_stok_item']??($pi>=0?self::sid($produk[$pi]):''));if($sid==='')$sid='STK-'.strtoupper(substr(bin2hex(random_bytes(6)),0,10));$master[]=['id_stok_item'=>$sid,'id_produk'=>(string)($p['id_produk']??($pi>=0?self::pid($produk[$pi]):'')),'kode_sku'=>$sku,'nama_produk'=>(string)($p['nama_produk']??($pi>=0?($produk[$pi]['nama']??''):'')),'stok_awal'=>0,'masuk_jahit'=>0,'masuk_vendor'=>0,'keluar_penjualan'=>0,'stok_akhir'=>0,'status_stok'=>'Habis','last_updated'=>$this->now()];$mi=count($master)-1;}
            $before=(float)($master[$mi]['stok_akhir']??$master[$mi]['stok']??0);$after=$before+$qtyIn-$qtyOut;if($after<0)throw new RuntimeException('Stok tidak mencukupi. Stok tersedia: '.$before.', keluar: '.$qtyOut);
            $jenis=strtoupper((string)($p['jenis_mutasi']??'ADJUSTMENT'));$master[$mi]['stok_akhir']=$after;$master[$mi]['stok']=$after;$master[$mi]['last_updated']=$this->now();$min=(float)($master[$mi]['stok_minimum']??$master[$mi]['min_alert']??0);$master[$mi]['status_stok']=$after<=0?'Habis':($after<=$min?'Menipis':'Aman');
            if(str_contains($jenis,'JAHIT')||$jenis==='IN_PRODUCTION')$master[$mi]['masuk_jahit']=(float)($master[$mi]['masuk_jahit']??0)+$qtyIn;
            if(str_contains($jenis,'VENDOR')||str_contains($jenis,'PURCHASE'))$master[$mi]['masuk_vendor']=(float)($master[$mi]['masuk_vendor']??0)+$qtyIn;
            if(str_contains($jenis,'SALE'))$master[$mi]['keluar_penjualan']=(float)($master[$mi]['keluar_penjualan']??0)+$qtyOut;
            if($pi>=0){$produk[$pi]['stok']=$after;if(empty($produk[$pi]['id_stok_item']))$produk[$pi]['id_stok_item']=$master[$mi]['id_stok_item'];$produk[$pi]['updated_at']=$this->now();}
            $movement=array_merge($p,['id_mutasi'=>$p['id_mutasi']??$this->id('MUT'),'tanggal'=>$p['tanggal']??date('Y-m-d'),'waktu'=>$p['waktu']??date('H:i:s'),'id_stok_item'=>$master[$mi]['id_stok_item'],'id_produk'=>$master[$mi]['id_produk']??($p['id_produk']??''),'kode_sku'=>$master[$mi]['kode_sku']??($p['kode_sku']??''),'qty_masuk'=>$qtyIn,'qty_keluar'=>$qtyOut,'stok_sebelum'=>$before,'stok_setelah'=>$after,'saldo_berjalan'=>$after,'operator'=>$p['operator']??($_SESSION['username']??'system'),'created_at'=>$this->now()]);$mut[]=$movement;
            $this->store->putMany(['master_stok'=>$master,'produk'=>$produk,'mutasi_stok'=>$mut]);$res=['status'=>'success','code'=>'STOCK_MOVEMENT_POSTED','data'=>['movement'=>$movement,'stok_sebelum'=>$before,'stok_setelah'=>$after]];
            if($key!==''){$st=$this->pdo->prepare('INSERT IGNORE INTO idempotency_keys(idempotency_key,module,reference_id,response_json) VALUES(?,?,?,?)');$st->execute([$key,'Inventory',(string)($p['id_referensi']??''),json_encode($res)]);}AuditService::log($this->pdo,'INVENTORY_MOVEMENT','Inventory',$movement['id_mutasi'],(string)($p['id_referensi']??''),null,$movement);return $res;
        }finally{$rel=$this->pdo->prepare('SELECT RELEASE_LOCK(?)');$rel->execute([$lockName]);}
    }
}
