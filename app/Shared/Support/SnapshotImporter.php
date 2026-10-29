<?php
final class SnapshotImporter {
    private PDO $pdo;
    public function __construct(PDO $pdo){ $this->pdo=$pdo; }

    public static function apiKey(string $sheet): string {
        $s = preg_replace('/([a-z0-9])([A-Z])/', '$1_$2', $sheet);
        $s = strtolower(preg_replace('/[^A-Za-z0-9]+/', '_', $s));
        return trim($s, '_');
    }
    public static function tableName(string $sheet): string { return 'jl_data_' . self::apiKey($sheet); }
    private static function sqlColumn(string $h): string {
        $c = strtolower(preg_replace('/[^A-Za-z0-9_]+/','_',trim($h)));
        $c = trim($c,'_'); if ($c==='') $c='col';
        if (preg_match('/^[0-9]/',$c)) $c='c_'.$c;
        return substr($c,0,60);
    }
    private static function columnNames(array $headers): array {
        $seen=[];$out=[];
        foreach($headers as $h){$base=self::sqlColumn((string)$h);$name=$base;$n=1;while(isset($seen[$name])){$n++;$suffix='__'.$n;$name=substr($base,0,60-strlen($suffix)).$suffix;}$seen[$name]=true;$out[]=$name;}
        return $out;
    }
    private function ensureTable(string $sheet, array $headers): string {
        $table = self::tableName($sheet);
        $this->pdo->exec("CREATE TABLE IF NOT EXISTS `{$table}` (
            _row_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            _record_key VARCHAR(190) NULL,
            _row_order INT NOT NULL DEFAULT 0,
            _data_json LONGTEXT NOT NULL,
            _created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            _updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_record_key(_record_key), INDEX idx_row_order(_row_order)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $existing=[]; foreach($this->pdo->query("SHOW COLUMNS FROM `{$table}`") as $r) $existing[$r['Field']]=true;
        $uniqueCols=self::columnNames($headers);
        foreach($uniqueCols as $col){
            if(isset($existing[$col]) || str_starts_with($col,'_')) continue;
            $this->pdo->exec("ALTER TABLE `{$table}` ADD COLUMN `{$col}` LONGTEXT NULL");
            $existing[$col]=true;
        }
        return $table;
    }
    private static function recordKey(array $row): string {
        foreach(['id','id_penjualan','id_mutasi','id_stok_item','id_bahan','id_produk','id_pegawai','id_supplier','id_dokumen','id_hutang','kode','kode_sku','kode_bahan','no_penjualan','no_faktur'] as $k){
            if(isset($row[$k]) && $row[$k]!=='' && $row[$k]!==null) return substr((string)$row[$k],0,190);
        }
        foreach($row as $k=>$v){ if(str_starts_with((string)$k,'id_') && $v!=='' && $v!==null) return substr((string)$v,0,190); }
        return '';
    }
    public function importFile(string $file, bool $replace=true): array {
        if(!is_file($file)) throw new RuntimeException('Snapshot database tidak ditemukan: '.$file);
        $data=json_decode(file_get_contents($file),true,512,JSON_THROW_ON_ERROR);
        $sheets=$data['sheets']??[]; $results=[]; $total=0;
        foreach($sheets as $sheet=>$payload){
            $headers=$payload['headers']??[]; $rows=$payload['rows']??[];
            $table=$this->ensureTable($sheet,$headers);
            $ownTx = !$this->pdo->inTransaction();
            if($ownTx) $this->pdo->beginTransaction();
            try{
                if($replace) $this->pdo->exec("DELETE FROM `{$table}`");
                $cols=self::columnNames($headers);
                $columnSql = $cols ? ',`'.implode('`,`',$cols).'`' : '';
                $placeSql = $cols ? ','.implode(',',array_fill(0,count($cols),'?')) : '';
                $st=$this->pdo->prepare("INSERT INTO `{$table}`(_record_key,_row_order,_data_json{$columnSql}) VALUES(?,?,?{$placeSql})");
                $i=0;
                foreach($rows as $row){
                    $i++; $params=[self::recordKey($row),$i,json_encode($row,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)];
                    foreach($headers as $h){$v=$row[$h]??null; if(is_array($v)||is_object($v))$v=json_encode($v,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES); if(is_bool($v))$v=$v?'1':'0'; $params[]=$v;}
                    $st->execute($params);
                }
                $count=(int)$this->pdo->query("SELECT COUNT(*) FROM `{$table}`")->fetchColumn();
                $api=self::apiKey($sheet); $checksum=hash('sha256',json_encode($rows,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));
                $map=$this->pdo->prepare("INSERT INTO system_table_map(sheet_name,api_key,table_name,headers_json,source_row_count,imported_row_count,checksum,updated_at) VALUES(?,?,?,?,?,?,?,NOW()) ON DUPLICATE KEY UPDATE api_key=VALUES(api_key),table_name=VALUES(table_name),headers_json=VALUES(headers_json),source_row_count=VALUES(source_row_count),imported_row_count=VALUES(imported_row_count),checksum=VALUES(checksum),updated_at=NOW()");
                $map->execute([$sheet,$api,$table,json_encode($headers,JSON_UNESCAPED_UNICODE),count($rows),$count,$checksum]);
                if($ownTx) $this->pdo->commit();
                $status=$count===count($rows)?'PASS':'FAIL';
                $results[]=['sheet'=>$sheet,'table'=>$table,'source'=>count($rows),'target'=>$count,'status'=>$status]; $total+=$count;
            }catch(Throwable $e){ if($ownTx && $this->pdo->inTransaction())$this->pdo->rollBack(); throw $e; }
        }
        return ['status'=>'success','sheets'=>count($results),'rows'=>$total,'results'=>$results,'source_metadata'=>$data['metadata']??[]];
    }
}
