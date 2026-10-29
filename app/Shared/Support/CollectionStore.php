<?php
class CollectionStore {
    private PDO $pdo;
    public function __construct(PDO $pdo){$this->pdo=$pdo;}
    public static function apiKey(string $name): string { return SnapshotImporter::apiKey($name); }
    private function map(string $name): ?array {
        $api=self::apiKey($name);
        $st=$this->pdo->prepare("SELECT * FROM system_table_map WHERE api_key=? OR LOWER(sheet_name)=LOWER(?) LIMIT 1");$st->execute([$api,$name]);$r=$st->fetch();return $r?:null;
    }
    private function ensureMap(string $name, array $rows=[]): array {
        $m=$this->map($name); if($m) return $m;
        $sheet=$name; $api=self::apiKey($sheet); $table=SnapshotImporter::tableName($sheet);
        $headers=[]; foreach($rows as $r) if(is_array($r)) foreach(array_keys($r) as $k) if(!in_array($k,$headers,true))$headers[]=$k;
        $tmp=['metadata'=>[],'sheets'=>[$sheet=>['headers'=>$headers,'rows'=>[]]]];
        $path=tempnam(sys_get_temp_dir(),'jlmap'); file_put_contents($path,json_encode($tmp)); (new SnapshotImporter($this->pdo))->importFile($path,true); @unlink($path);
        return $this->map($name) ?: ['sheet_name'=>$sheet,'api_key'=>$api,'table_name'=>$table,'headers_json'=>json_encode($headers)];
    }
    public function get(string $name, bool $fresh=false): array {
        $m=$this->map($name); if(!$m) return [];
        $table=$m['table_name']; $out=[];
        $q=$this->pdo->query("SELECT _data_json FROM `{$table}` ORDER BY _row_order,_row_id");
        while($r=$q->fetch()){ $x=json_decode($r['_data_json'],true); if(is_array($x))$out[]=$x; }
        return $out;
    }
    public function put(string $name, array $rows): array {
        $m=$this->ensureMap($name,$rows); $table=$m['table_name'];
        $headers=[]; foreach($rows as $r) if(is_array($r)) foreach(array_keys($r) as $k) if(!in_array($k,$headers,true))$headers[]=$k;
        $imp=new SnapshotImporter($this->pdo);
        $tmp=['metadata'=>[],'sheets'=>[$m['sheet_name']=>['headers'=>$headers,'rows'=>array_values($rows)]]];
        $path=tempnam(sys_get_temp_dir(),'jlsync');file_put_contents($path,json_encode($tmp,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));
        $res=$imp->importFile($path,true);@unlink($path);
        return ['status'=>'success','tab'=>$name,'rows'=>count($rows),'table'=>$table,'database'=>'MYSQL_LOCAL'];
    }

    public function putMany(array $map): array {
        $ownTx=!$this->pdo->inTransaction(); if($ownTx)$this->pdo->beginTransaction();
        try{$r=[];foreach($map as $name=>$rows){if(is_array($rows))$r[$name]=$this->put((string)$name,$rows);}if($ownTx)$this->pdo->commit();return ['status'=>'success','results'=>$r,'tabs'=>count($r)];}
        catch(Throwable $e){if($ownTx&&$this->pdo->inTransaction())$this->pdo->rollBack();throw $e;}
    }
    public function allKnown(): array {
        $out=[];$q=$this->pdo->query("SELECT sheet_name,api_key FROM system_table_map ORDER BY id");
        while($m=$q->fetch()){$out[$m['api_key']]=$this->get($m['api_key']);}
        foreach(['rekening','produk','bahan','bahan_hpp','hpp_model_bom','hpp_history','bahan_hpp_history','pegawai','supplier','pembelian','pembelian_item','potong','potong_item','jahit','jahit_item','keuangan','kasbon','profil','distribusi','pengguna','hak_akses','master_stok','mutasi_stok','pembelian_vendor','riwayat_bayar_hutang','hutang_konveksi','riwayat_bayar_hutang_konveksi','template_dokumen','penjualan','penjualan_item','penjualan_koreksi','material_alias','quality_control','mutasi_stok_konveksi','hutangsupplier','marketplace_product_mapping','marketplace_sync_queue'] as $k) if(!isset($out[$k]))$out[$k]=[];
        return $out;
    }
}
