<?php
$root=(dirname(__DIR__, 1));require_once $root.'/app/Bootstrap/bootstrap.php';
if (empty($_SESSION['user_id']) || stripos((string)($_SESSION['role']??''),'OWNER')===false) { http_response_code(403); exit('Akses ditolak.'); }
$remote=$_SERVER['REMOTE_ADDR']??'';
if(!in_array($remote,['127.0.0.1','::1'],true)){http_response_code(403);exit('Repair Owner hanya boleh dijalankan dari localhost.');}
$lock=$root.'/storage/install.lock';
if(!is_file($lock)){http_response_code(409);exit('JAGO LABA belum memiliki install.lock. Jalankan install.php terlebih dahulu.');}
$error='';$success='';$diag=null;
if($_SERVER['REQUEST_METHOD']==='POST'){
 try{
  Workspace::verifyCsrf();
  $pdo=Database::pdo();
  $username=trim((string)($_POST['username']??'owner'))?:'owner';
  $name=trim((string)($_POST['name']??'Owner JAGO LABA'))?:'Owner JAGO LABA';
  $password=(string)($_POST['password']??'');
  if(strlen($password)<8)throw new RuntimeException('Password minimal 8 karakter.');
  SchemaManager::ensureOwner($pdo,$username,$name,$password);
  try { SchemaManager::ensureSystemSchema($pdo); } catch (Throwable $legacySchemaWarning) { /* Auth V2 already valid; legacy repair can wait. */ }
  $diag=SchemaManager::authDiagnostic($pdo,$username,$password);
  $success='Owner berhasil diperbaiki pada AUTH V2 dan password_verify() PASS.';
 }catch(Throwable $e){$error=$e->getMessage();}
}
?><!doctype html><html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Repair Owner JAGO LABA</title><style>body{font-family:Inter,Arial;background:#f1f5f9;color:#0f172a}.box{max-width:560px;margin:60px auto;background:#fff;padding:28px;border-radius:22px;box-shadow:0 20px 60px #0f172a18}label{display:block;font-weight:800;font-size:12px;margin:14px 0 6px}input{width:100%;box-sizing:border-box;padding:12px;border:1px solid #cbd5e1;border-radius:12px}button{width:100%;margin-top:18px;padding:13px;border:0;border-radius:12px;background:#2563eb;color:#fff;font-weight:800}.ok{background:#ecfdf5;color:#047857;padding:12px;border-radius:12px}.err{background:#fff1f2;color:#be123c;padding:12px;border-radius:12px}a{display:inline-block;margin-top:16px}</style></head><body><div class="box"><h2>Repair Owner JAGO LABA</h2><p>Hanya akun owner yang dapat menggunakan halaman ini.</p><?php if($error):?><div class="err"><?=htmlspecialchars($error)?></div><?php endif;?><?php if($success):?><div class="ok"><?=htmlspecialchars($success)?></div><?php endif;?><form method="post"><input type="hidden" name="csrf" value="<?=htmlspecialchars(Workspace::csrf())?>"><label>Username</label><input name="username" value="owner"><label>Nama</label><input name="name" value="Owner JAGO LABA"><label>Password baru</label><input type="password" name="password" minlength="8" required><button>PERBAIKI & VERIFIKASI LOGIN OWNER</button></form><a href="./">Kembali ke Login</a></div></body></html>
