<?php
declare(strict_types=1);
// Explicit disposable local target only. Never reads the production connection config.
$stage = 'D:/JAGO_LABA_STAGING/laravel-step1-4-20261101';
$port = 3320;
$pdo = new PDO("mysql:host=127.0.0.1;port=$port;charset=utf8mb4", 'root', '', [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$expected = realpath($stage.'/mysql-data');
if (strtolower(rtrim(str_replace('\\','/', (string)$pdo->query('SELECT @@datadir')->fetchColumn()), '/')) !== strtolower(str_replace('\\','/', $expected))) throw new RuntimeException('Unexpected datadir');
$resume=($argv[1]??'')==='--resume';
$targets = ['jl_s14_market'=>'jago_laba.sql','jl_s14_konveksi'=>'jago_laba_konveksi.sql','jl_s14_schema_rehearsal_v2'=>'jago_laba.sql'];
$evidence = [];
foreach ($targets as $schema=>$dump) {
    if($resume && in_array($schema,['jl_s14_market','jl_s14_konveksi'],true)) {
        require_once __DIR__.'/count-sql-dump-rows.php';
        $expectedCounts=dumpCounts($stage.'/backup/'.$dump);
        $actualTables=$pdo->query("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA='$schema' AND TABLE_TYPE='BASE TABLE' ORDER BY TABLE_NAME")->fetchAll(PDO::FETCH_COLUMN);
        sort($actualTables);
        if(array_keys($expectedCounts)!==$actualTables) throw new RuntimeException('Interrupted baseline has incomplete tables');
        foreach($expectedCounts as $table=>$count) if((int)$pdo->query("SELECT COUNT(*) FROM `$schema`.`$table`")->fetchColumn()!==$count) throw new RuntimeException('Interrupted baseline has incomplete rows');
        preg_match_all('/ADD CONSTRAINT `([^`]+)` FOREIGN KEY/',file_get_contents($stage.'/backup/'.$dump),$constraints);
        $actualFks=$pdo->query("SELECT CONSTRAINT_NAME FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA='$schema' ORDER BY CONSTRAINT_NAME")->fetchAll(PDO::FETCH_COLUMN);
        sort($actualFks);
        $expectedFks=$constraints[1]; sort($expectedFks);
        if($expectedFks!==$actualFks) throw new RuntimeException('Interrupted baseline has incomplete FK definitions');
        $evidence[$schema]=['dump_sha256'=>hash_file('sha256',$stage.'/backup/'.$dump),'resume_verified_all_rows'=>true,'resume_verified_all_fk_names'=>true,'integrity_approved'=>false];
        continue;
    }
    if ($pdo->query("SELECT COUNT(*) FROM information_schema.schemata WHERE schema_name='$schema'")->fetchColumn()) throw new RuntimeException('Refusing to overwrite existing schema');
    if($resume && $schema==='jl_s14_schema_rehearsal_v2') {
        $triggers=$pdo->query('SHOW TRIGGERS FROM jl_s14_market')->fetchAll(PDO::FETCH_ASSOC);
        $pdo->exec("CREATE DATABASE `$schema` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $pdo->exec('SET FOREIGN_KEY_CHECKS=0');
        $pdo->exec("USE `$schema`");
        foreach($pdo->query('SHOW TABLES FROM jl_s14_market')->fetchAll(PDO::FETCH_COLUMN) as $table) {
            if(!preg_match('/^[a-z0-9_]+$/',$table)) throw new RuntimeException('Unsafe source identifier');
            $ddl=$pdo->query("SHOW CREATE TABLE jl_s14_market.`$table`")->fetch(PDO::FETCH_NUM)[1];
            $pdo->exec($ddl);
            $columns=[];
            foreach($pdo->query("SHOW COLUMNS FROM jl_s14_market.`$table`")->fetchAll(PDO::FETCH_ASSOC) as $col) {
                if(!preg_match('/(?:VIRTUAL|STORED) GENERATED/',$col['Extra'])) $columns[]='`'.$col['Field'].'`';
            }
            $list=implode(',',$columns);
            $pdo->exec("INSERT INTO `$schema`.`$table` ($list) SELECT $list FROM jl_s14_market.`$table`");
        }
        foreach($triggers as $trigger) {
            if(!preg_match('/^[a-z0-9_]+$/',$trigger['Trigger'])) throw new RuntimeException('Unsafe trigger identifier');
            $definition=$pdo->query('SHOW CREATE TRIGGER jl_s14_market.`'.$trigger['Trigger'].'`')->fetch(PDO::FETCH_ASSOC);
            $pdo->exec('SET SESSION sql_mode='.$pdo->quote($definition['sql_mode']));
            $statement=$definition['SQL Original Statement'];
            // Bind only explicit source-schema identifiers to the diagnostic clone.
            $statement=str_replace('`jl_s14_market`.','`'.$schema.'`.',$statement);
            $pdo->exec($statement);
        }
        $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
        $evidence[$schema]=['clone_of'=>'jl_s14_market','source_ddl_copied'=>true,'source_rows_copied'=>true,'triggers_copied'=>count($triggers),'incomplete_previous_clone'=>'jl_s14_schema_rehearsal (not reused or deleted)','integrity_approved'=>false];
        continue;
    }
    $sql = file_get_contents($stage.'/backup/'.$dump);
    if (preg_match('/^\s*(?:USE\s|CREATE\s+DATABASE|DROP\s+DATABASE)/im', $sql)) throw new RuntimeException('Dump selects another schema');
    $pdo->exec("CREATE DATABASE `$schema` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    // Dump remains unchanged. Diagnostic copy allows replay then audits all FK constraints.
    $input = $stage.'/private/import.sql';
    file_put_contents($input, "SET FOREIGN_KEY_CHECKS=0;\n".$sql."\nSET FOREIGN_KEY_CHECKS=1;\n");
    $descriptors = [0=>['file',$input,'r'],1=>['file',$stage.'/private/import.out','w'],2=>['file',$stage.'/private/import.err','w']];
    $proc = proc_open(['D:/xampp/mysql/bin/mysql.exe','--protocol=tcp','--host=127.0.0.1','--port=3320','--user=root','--default-character-set=utf8mb4',$schema], $descriptors, $pipes);
    $exit = proc_close($proc);
    if ($exit !== 0) throw new RuntimeException('Restore failed; inspect private import.err, do not publish it');
    $evidence[$schema] = ['dump_sha256'=>hash_file('sha256', $stage.'/backup/'.$dump),'import_exit'=>$exit,'fk_replay_disabled'=>true,'integrity_approved'=>false];
}
$password = bin2hex(random_bytes(24));
$pdo->exec("CREATE USER 'jl_s14_read'@'127.0.0.1' IDENTIFIED BY '$password'");
foreach (['jl_s14_market','jl_s14_konveksi'] as $schema) $pdo->exec("GRANT SELECT ON `$schema`.* TO 'jl_s14_read'@'127.0.0.1'");
$config = ['host'=>'127.0.0.1','port'=>3320,'user'=>'jl_s14_read','pass'=>$password,'name'=>'jl_s14_konveksi','isolated'=>true,'company_key'=>'LOCAL-COMPANY','driver'=>'mysql','charset'=>'utf8mb4'];
file_put_contents($stage.'/private/konveksi.php', "<?php\nreturn ".var_export($config,true).";\n");
$config['name'] = 'jl_s14_market';
file_put_contents($stage.'/private/market.php', "<?php\nreturn ".var_export($config,true).";\n");
// Root credentials are restricted to this fresh disposable MariaDB datadir.
$adminPassword = bin2hex(random_bytes(24));
$pdo->exec("ALTER USER 'root'@'localhost' IDENTIFIED BY '$adminPassword'");
file_put_contents($stage.'/private/admin.php', "<?php\nreturn ".var_export(['host'=>'127.0.0.1','port'=>3320,'user'=>'root','pass'=>$adminPassword],true).";\n");
$key = 'D:/xampp/apache/conf/extra/jago-laba-marketplace.key';
$keyEvidence = ['present'=>false];
if (is_file($key)) {
    $bytes = file_get_contents($key); $decoded = base64_decode(trim($bytes),true);
    if ($decoded === false || strlen($decoded)!==32) throw new RuntimeException('Key format invalid');
    copy($key,$stage.'/private/marketplace.key');
    $keyEvidence = ['present'=>true,'decoded_bytes'=>32,'copy_matches'=>hash_file('sha256',$key)===hash_file('sha256',$stage.'/private/marketplace.key')];
}
file_put_contents($stage.'/evidence/restore.json',json_encode(['targets'=>$evidence,'key'=>$keyEvidence,'provider_calls'=>0,'scheduler_started'=>false],JSON_PRETTY_PRINT)."\n");
echo "Restored 3 diagnostic schemas; SELECT-only accounts and isolated Konveksi config ready.\n";
