<?php
declare(strict_types=1);
/** Explicit domain connection; credentials installed by the reviewed CLI cutover. */
final class KonveksiDatabase
{
 private static ?PDO $pdo=null;
 public static function config():array{
  $path=dirname(__DIR__,4).'/storage/private/konveksi/database.php';
  if(is_file($path)){$c=require $path;if(!is_array($c))throw new RuntimeException('Konfigurasi database Konveksi tidak valid.');return $c;}
  return Database::config()['db']+['company_key'=>'LOCAL-COMPANY','isolated'=>false];
 }
 public static function pdo():PDO{
  if(self::$pdo)return self::$pdo;$c=self::config();
  if(empty($c['isolated']))return Database::pdo();
  self::$pdo=new PDO(sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',$c['host'],$c['port'],$c['name']),$c['user'],$c['pass'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);self::$pdo->exec("SET time_zone='+07:00'");return self::$pdo;
 }
 public static function assertCompany():void{
  $c=self::config();$key=(string)($_SESSION['company_key']??$_SESSION['company_id']??'LOCAL-COMPANY');
  // Legacy data has one verified company binding; never expose it to another company.
  if($key!==($c['company_key']??'LOCAL-COMPANY'))throw new DomainException('Data Konveksi perusahaan ini belum dimigrasikan.');
 }
}
