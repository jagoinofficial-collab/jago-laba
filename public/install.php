<?php
$root=(dirname(__DIR__, 1));require_once $root.'/app/Bootstrap/bootstrap.php';
$lockFile=$root.'/storage/install.lock';$error='';$success='';$report=null;$preflight=[];
function pf(string $label,bool $ok,string $detail=''):array{return ['label'=>$label,'ok'=>$ok,'detail'=>$detail];}
$preflight[]=pf('PHP >= 8.2',version_compare(PHP_VERSION,'8.2.0','>='),PHP_VERSION);
$preflight[]=pf('PDO MySQL',extension_loaded('pdo_mysql'),extension_loaded('pdo_mysql')?'Aktif':'Aktifkan extension pdo_mysql');
$preflight[]=pf('JSON',extension_loaded('json'),'');
$preflight[]=pf('Snapshot database',is_file($root.'/database/legacy/current_database_snapshot.json'),$root.'/database/legacy/current_database_snapshot.json');
$preflight[]=pf('Storage writable',is_writable($root.'/storage')||@chmod($root.'/storage',0775),'');
$canInstall=!in_array(false,array_column($preflight,'ok'),true);

function migrateLegacyUsers(PDO $pdo,CollectionStore $store): int {
    $users=$store->get('pengguna');
    $count=0;
    foreach($users as $u){
        if(!is_array($u)) continue;
        $username=trim((string)($u['username']??''));
        if($username==='') continue;
        // Centralized compatibility layer handles legacy `nama`, missing user_uuid,
        // status enum differences and password hashing.
        $payload=[
            'user_uuid'=>(string)($u['user_uuid']??$u['id']??''),
            'username'=>$username,
            'name'=>(string)($u['nama']??$u['name']??$username),
            'email'=>(string)($u['email']??''),
            'role_id'=>(string)($u['role_id']??'ROLE-STAFF'),
            'status'=>(string)($u['status']??'Aktif'),
            'must_change_password'=>1,
        ];
        if(trim((string)($u['password_hash']??''))!=='') $payload['password_hash']=(string)$u['password_hash'];
        elseif(trim((string)($u['password']??''))!=='') $payload['password']=(string)$u['password'];
        SchemaManager::upsertUser($pdo,$payload);
        $count++;
    }
    return $count;
}

if($_SERVER['REQUEST_METHOD']==='POST'){
    try{
        if(!$canInstall)throw new RuntimeException('Preflight belum PASS. Perbaiki komponen yang berwarna merah.');
        $mode=(string)($_POST['mode']??'install');$username=trim((string)($_POST['username']??'owner'))?:'owner';$ownerName=trim((string)($_POST['owner_name']??'Owner JAGO LABA'))?:'Owner JAGO LABA';$password=(string)($_POST['password']??'owner123');if(strlen($password)<8)throw new RuntimeException('Password Owner minimal 8 karakter.');
        SchemaManager::createDatabase();$pdo=Database::pdo();SchemaManager::ensureSystemSchema($pdo);$store=new CollectionStore($pdo);$backup=new BackupService($pdo,$store);
        $existingMapped=(int)$pdo->query('SELECT COUNT(*) FROM system_table_map')->fetchColumn();$oldTables=(int)$pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE()")->fetchColumn();$preBackup=null;if($oldTables>6){try{$preBackup=$backup->create('PRE_INSTALL');}catch(Throwable $be){$preBackup=['status'=>'warning','message'=>$be->getMessage()];}}
        if($mode==='repair' && is_file($lockFile)){
            SchemaManager::ensureOwner($pdo,$username,$ownerName,$password);$authTest=SchemaManager::authDiagnostic($pdo,$username,$password);$post=$backup->create('REPAIR');$report=['mode'=>'REPAIR','pre_backup'=>$preBackup,'post_backup'=>$post,'auth_self_test'=>$authTest,'message'=>'Repair selesai tanpa mengimpor ulang data sumber.'];$success='Repair JAGO LABA LOCAL berhasil. Login Owner sudah diverifikasi oleh password_verify().';
        }else{
            $migrationId='MIG-ONEPHASE-'.date('Ymd-His');$pdo->prepare("INSERT INTO migration_log(migration_id,source_name,target_name,status) VALUES(?,?,?,'RUNNING') ON DUPLICATE KEY UPDATE started_at=NOW(),status='RUNNING'")->execute([$migrationId,'JAGO_LABA_DB_KONVEKSI','jago_laba']);
            $import=(new SnapshotImporter($pdo))->importFile($root.'/database/legacy/current_database_snapshot.json',true);$legacyUsers=migrateLegacyUsers($pdo,$store);SchemaManager::ensureOwner($pdo,$username,$ownerName,$password);$authTest=SchemaManager::authDiagnostic($pdo,$username,$password);
            $failed=array_values(array_filter($import['results'],fn($x)=>$x['status']!=='PASS'));if($failed)throw new RuntimeException('Validasi row count gagal pada '.count($failed).' tabel.');
            $pdo->prepare("UPDATE migration_log SET completed_at=NOW(),status='PASS',details_json=? WHERE migration_id=?")->execute([json_encode($import,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),$migrationId]);AuditService::log($pdo,'ONE_PHASE_MIGRATION','System',null,$migrationId,null,$import);
            $post=$backup->create('POST_INSTALL');$lock=['installed_at'=>date(DATE_ATOM),'version'=>Database::config()['app']['version'],'migration_id'=>$migrationId,'source_sheets'=>$import['sheets'],'source_rows'=>$import['rows'],'owner'=>$username];file_put_contents($lockFile,json_encode($lock,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));$report=['mode'=>'INSTALL','migration_id'=>$migrationId,'pre_backup'=>$preBackup,'import'=>$import,'legacy_users'=>$legacyUsers,'auth_self_test'=>$authTest,'post_backup'=>$post];$success='Instalasi ONE PHASE berhasil. Database lama sudah dimigrasikan, dibackup, dan login Owner sudah diverifikasi.';
        }
    }catch(Throwable $e){$error=$e->getMessage();try{if(isset($pdo)&&$pdo instanceof PDO)AuditService::log($pdo,'INSTALL_FAILED','System',null,null,null,null,'ERROR',$error);}catch(Throwable $ignore){}}
}
$locked=is_file($lockFile);
?><!doctype html><html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Install JAGO LABA Local</title><style>body{font-family:Inter,Arial;background:#f1f5f9;color:#0f172a;margin:0}.wrap{max-width:980px;margin:40px auto;padding:20px}.card{background:#fff;border:1px solid #dbe3ef;border-radius:24px;padding:28px;box-shadow:0 18px 50px rgba(15,23,42,.08)}h1{margin:0 0 8px}.grid{display:grid;grid-template-columns:1fr 1fr;gap:12px}.pf{padding:12px;border-radius:14px;background:#f8fafc;border:1px solid #e2e8f0}.ok{color:#047857}.bad{color:#be123c}.alert{padding:14px;border-radius:14px;margin:15px 0}.err{background:#fff1f2;color:#be123c}.succ{background:#ecfdf5;color:#047857}label{display:block;font-size:12px;font-weight:800;margin:12px 0 5px}input{width:100%;box-sizing:border-box;padding:12px;border-radius:12px;border:1px solid #cbd5e1}button{margin-top:18px;width:100%;padding:13px;border:0;border-radius:12px;background:#2563eb;color:white;font-weight:800;cursor:pointer}table{width:100%;border-collapse:collapse;margin-top:18px;font-size:12px}td,th{padding:8px;border-bottom:1px solid #e2e8f0;text-align:left}.links{display:flex;gap:10px;flex-wrap:wrap;margin-top:16px}.links a{padding:10px 14px;background:#0f172a;color:white;text-decoration:none;border-radius:10px}@media(max-width:700px){.grid{grid-template-columns:1fr}.wrap{margin:10px auto}}</style></head><body><div class="wrap"><div class="card"><h1>JAGO LABA Local — ONE PHASE</h1><p>Instalasi XAMPP + PHP + MySQL + migrasi database sumber + backup otomatis.</p><div class="grid"><?php foreach($preflight as $x):?><div class="pf"><strong class="<?=$x['ok']?'ok':'bad'?>"><?=$x['ok']?'✓':'✕'?> <?=htmlspecialchars($x['label'])?></strong><div style="font-size:11px;color:#64748b;margin-top:4px"><?=htmlspecialchars($x['detail'])?></div></div><?php endforeach;?></div><?php if($error):?><div class="alert err"><strong>Instalasi gagal:</strong> <?=htmlspecialchars($error)?></div><?php endif;?><?php if($success):?><div class="alert succ"><strong><?=htmlspecialchars($success)?></strong></div><?php endif;?><?php if(!$success):?><form method="post"><input type="hidden" name="mode" value="<?=$locked?'repair':'install'?>"><label>Username Owner</label><input name="username" value="owner" required><label>Nama Owner</label><input name="owner_name" value="Owner JAGO LABA" required><label>Password Owner</label><input type="password" name="password" value="owner123" minlength="8" required><button type="submit"><?=$locked?'Repair Non-Destruktif + Reset Owner':'INSTALL ONE PHASE + IMPORT DATABASE'?></button></form><?php endif;?><?php if($report):?><h3>Hasil Proses</h3><?php if(!empty($report['auth_self_test'])):?><div class="alert succ"><strong>✓ Self-test Login Owner PASS</strong><div style="font-size:12px;margin-top:4px">Akun <?=htmlspecialchars($report['auth_self_test']['username']??'owner')?> ditemukan <?=intval($report['auth_self_test']['rows']??0)?> record dan password installer sudah lolos password_verify().</div></div><?php endif;?><?php if(!empty($report['import']['results'])):?><table><thead><tr><th>Sheet</th><th>Table MySQL</th><th>Source</th><th>Target</th><th>Status</th></tr></thead><tbody><?php foreach($report['import']['results'] as $r):?><tr><td><?=htmlspecialchars($r['sheet'])?></td><td><?=htmlspecialchars($r['table'])?></td><td><?=$r['source']?></td><td><?=$r['target']?></td><td class="<?=$r['status']==='PASS'?'ok':'bad'?>"><?=$r['status']?></td></tr><?php endforeach;?></tbody></table><?php endif;?><div class="links"><a href="./">Buka JAGO LABA</a><a href="api/health.php" target="_blank">Health Check</a><a href="http://localhost/phpmyadmin" target="_blank">phpMyAdmin</a></div><?php endif;?><?php if($locked&&!$success):?><div class="alert succ">Aplikasi sudah memiliki <code>install.lock</code>. Mode ini hanya memperbaiki schema/login dan membuat backup; data aplikasi tidak diimpor ulang.</div><div class="links"><a href="./">Buka JAGO LABA</a><a href="api/health.php" target="_blank">Health Check</a></div><?php endif;?></div></div></body></html>
