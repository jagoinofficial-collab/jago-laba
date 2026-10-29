<?php
declare(strict_types=1);
/** Compatibility repository: allowlisted domain data, transactional DML; never runtime DDL/import. */
final class KonveksiCollectionStore extends CollectionStore
{
 public const COLLECTIONS=['rekening','produk','bahan','bahan_hpp','hpp_model_bom','hpp_history','bahan_hpp_history','pegawai','supplier','pembelian','pembelian_item','potong','potong_item','jahit','jahit_item','keuangan','kasbon','profil','distribusi','pengguna','hak_akses','master_stok','mutasi_stok','pembelian_vendor','riwayat_bayar_hutang','hutang_konveksi','riwayat_bayar_hutang_konveksi','template_dokumen','penjualan','penjualan_item','penjualan_koreksi','material_alias','quality_control','mutasi_stok_konveksi','hutangsupplier','hutang_supplier'];
 public function __construct(private PDO $db){parent::__construct($db);}
 public static function key(string $name):string{$key=SnapshotImporter::apiKey($name);if($key==='hutangsupplier')$key='hutang_supplier';if(!in_array($key,self::COLLECTIONS,true))throw new DomainException('Kumpulan data tidak termasuk domain Konveksi.');return $key;}
 public function get(string $name,bool $fresh=false):array{self::key($name);return parent::get($name,$fresh);}
 public function resetEpoch():string{ $q=$this->db->query("SELECT setting_value FROM system_settings WHERE setting_key='KONVEKSI_RESET_EPOCH'");return (string)($q->fetchColumn()?:'0');}
 public function versions():array{$out=[];foreach(self::COLLECTIONS as $key)$out[$key]=hash('sha256',json_encode($this->get($key),JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR));return $out;}
 public function allKnown():array{
  $out=[];foreach(self::COLLECTIONS as $key)$out[$key]=$this->get($key);
  $missing=0;$negative=0;$identities=[];$duplicates=0;
  foreach($out['produk'] as $p){$id=(string)($p['id_stok_item']??'');if($id==='')$missing++;elseif(isset($identities[$id]))$duplicates++;else $identities[$id]=true;}
  foreach($out['master_stok'] as $r)if(isset($r['stok_akhir'])&&is_numeric($r['stok_akhir'])&&$r['stok_akhir']<0)$negative++;
  $out['__meta']=['versions'=>$this->versions(),'domain'=>'konveksi','database_isolated'=>(bool)(KonveksiDatabase::config()['isolated']??false),'reset_epoch'=>$this->resetEpoch(),'loaded_at'=>date(DATE_ATOM),'health'=>['missing_stock_ids'=>$missing,'duplicate_stock_ids'=>$duplicates,'negative_stock'=>$negative],'company_bound'=>true];return $out;
 }
 public function put(string $name,array $rows):array{
  $key=self::key($name);foreach($rows as $row)if(!is_array($row))throw new InvalidArgumentException('Format baris data tidak valid.');
  $q=$this->db->prepare('SELECT * FROM system_table_map WHERE api_key=?');$q->execute([$key]);$map=$q->fetch();
  if(!$map)throw new RuntimeException('Penyimpanan Konveksi belum disiapkan. Jalankan installer domain.');
  $table=(string)$map['table_name'];if(!preg_match('/^jl_data_[a-z0-9_]+$/',$table))throw new RuntimeException('Pemetaan tabel tidak valid.');
  $columns=$this->db->query("SHOW COLUMNS FROM `$table`")->fetchAll(PDO::FETCH_COLUMN);$projection=array_values(array_filter($columns,static fn($c)=>!str_starts_with($c,'_')));
  $own=!$this->db->inTransaction();if($own)$this->db->beginTransaction();
  try{
   $this->db->exec("DELETE FROM `$table`");
   $sql="INSERT INTO `$table` (`_record_key`,`_row_order`,`_data_json`".($projection?',`'.implode('`,`',$projection).'`':'').') VALUES ('.implode(',',array_fill(0,3+count($projection),'?')).')';$insert=$this->db->prepare($sql);
   $i=0;foreach($rows as $row){$id='';foreach(['id','id_penjualan','id_mutasi','id_stok_item','id_bahan','id_produk','id_pegawai','id_supplier','id_hutang','kode'] as $field)if(isset($row[$field])&&$row[$field]!==''){$id=substr((string)$row[$field],0,190);break;}$args=[$id,++$i,json_encode($row,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)];foreach($projection as $column){$v=$row[$column]??null;$args[]=is_array($v)||is_object($v)?json_encode($v,JSON_THROW_ON_ERROR):(is_bool($v)?(int)$v:$v);}$insert->execute($args);}
   $checksum=hash('sha256',json_encode(array_values($rows),JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR));
   $this->db->prepare('UPDATE system_table_map SET imported_row_count=?,source_row_count=?,checksum=?,updated_at=NOW() WHERE api_key=?')->execute([count($rows),count($rows),$checksum,$key]);
   if($own)$this->db->commit();return ['status'=>'success','tab'=>$key,'rows'=>count($rows)];
  }catch(Throwable $e){if($own&&$this->db->inTransaction())$this->db->rollBack();throw $e;}
 }
 public function putMany(array $map):array{
  foreach($map as $name=>$rows){self::key((string)$name);if(!is_array($rows))throw new InvalidArgumentException('Format batch tidak valid.');}
  $own=!$this->db->inTransaction();if($own)$this->db->beginTransaction();try{$out=[];foreach($map as $name=>$rows)$out[$name]=$this->put((string)$name,$rows);if($own)$this->db->commit();return ['status'=>'success','results'=>$out];}catch(Throwable $e){if($own&&$this->db->inTransaction())$this->db->rollBack();throw $e;}
 }
}
