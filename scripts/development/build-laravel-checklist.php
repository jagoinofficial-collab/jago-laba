<?php
declare(strict_types=1);

// Source-only inventory. Does not bootstrap either application or access DB/provider.
$root = dirname(__DIR__, 2);
$rows = [];
$contracts = [];
$section = '';
foreach (file($root.'/docs/architecture/endpoint-inventory-matrix.md') as $line) {
    if (preg_match('/^## (\d+)\./', $line, $m)) $section = $m[1];
    if (in_array($section, ['2','3','4','5','6','7'], true) && preg_match('/^\| ([^|]+)\|/', $line, $m)) {
        preg_match_all('/`([^`]+)`/', $m[1], $keys);
        foreach($keys[1] as $key) $contracts[$key] = trim($line);
    }
}
$add = function(string $kind, string $name, string $source, string $contractKey = '') use (&$rows, $root, $contracts): void {
    $key = $kind.':'.$name;
    if (isset($rows[$key])) return;
    $status = 'belum'; $target = ''; $test = ''; $step = 6;
    if ($kind === 'rpc' || $kind === 'konveksi-menu') $step = 8;
    if (preg_match('/document|shipping|withdrawal|export|template/i', $name)) $step = 9;
    if (preg_match('/oauth|sync|worker|cron|webhook|fulfillment|price|connection|partner/i', $name)) $step = 11;
    if (preg_match('/login|logout|workspace|Login/', $name)) $step = 5;
    if ($kind === 'ajax' && !str_starts_with($name, 'get_') && !in_array($name, ['simulate_price'], true) && $step === 6) $step = 7;
    if ($kind === 'worker') $step = 10;
    if ($kind === 'rpc' && in_array($name, ['prosesLoginBackend','loadSemuaData'], true)) {
        $status = 'pilot'; $target = 'POST /api/rpc.php'; $test = 'AuthScopeTest.php;PilotReadOnlyTest.php';
    }
    if ($kind === 'ajax' && $name === 'add_expense') {
        $status = 'pilot'; $target = 'POST /api/marketplace-ajax.php; POST /pilot/marketplace/expenses'; $test = 'PilotReadOnlyTest.php';
    }
    if ($kind === 'ajax' && $name === 'get_order_detail') {
        $status = 'sebagian'; $target = 'GET /pilot/marketplace/orders/{order}/detail; URL AJAX lama belum dipetakan'; $test = 'PilotReadOnlyTest.php';
    }
    if ($kind === 'public' && in_array($name, ['index.php','marketplace.php','api/rpc.php','api/marketplace-ajax.php','api/marketplace-oauth.php'], true)) {
        $status = 'sebagian'; $target = 'Lihat laravel-foundation/routes/web.php; hanya subset kontrak';
    }
    if ($kind === 'public' && in_array($name, ['api/health.php','api/logout.php','api/workspace.php'], true)) {
        $status = 'pilot'; $target = '/'.$name; $test = 'AuthScopeTest.php';
    }
    if (in_array($name, ['save_api_credentials','update_stock_allocation','run_safety_buffer_sync','trigger_cron_worker','api/marketplace-webhook.php'], true)) $status = 'stub-legacy-keputusan-pemilik';
    if (in_array($name, ['install.php','repair_owner.php'], true)) $status = 'utilitas-operasional-review-pemilik';
    if($target==='') $target=match($kind) {
        'rpc'=>'PROPOSED compatibility POST /api/rpc.php method='.$name,
        'ajax'=>'PROPOSED compatibility /api/marketplace-ajax.php?action='.$name,
        'api-action'=>'PROPOSED compatibility /api/'.str_replace(':','?action=',$name),
        'public'=>'PROPOSED compatibility /'.$name.'; activate only after parity',
        'konveksi-menu'=>'PROPOSED /pilot/konveksi?tab='.rawurlencode($name).' + matching Vue domain component',
        'marketplace-view'=>'PROPOSED Inertia/Vue component replacing legacy view '.$name.'; retain caller URL from source inventory',
        'worker'=>'PROPOSED bounded Laravel Command/Job for '.$name.'; single scheduler owner',
        default=>'review per feature',
    };
    $text = file_get_contents($root.'/'.$source);
    $line = 1;
    if (($pos = strpos($text, $name)) !== false) $line = substr_count(substr($text, 0, $pos), "\n") + 1;
    $tables = []; preg_match_all('/\b(?:FROM|JOIN|INTO|UPDATE|TABLE(?: IF NOT EXISTS)?)\s+`?([a-z][a-z0-9_]+)/i', $text, $m);
    foreach ($m[1] as $table) if (str_contains($table, '_')) $tables[$table] = true;
    preg_match_all('/\$_(?:GET|POST|FILES|REQUEST)\s*\[\s*[\'"]([^\'"]+)[\'"]\s*\]/', $text, $inputMatches);
    $contract = $contracts[$contractKey ?: $name] ?? '';
    $rows[$key] = [
        'id' => strtoupper(str_replace('-', '_', $kind)).'-'.substr(hash('sha256', $key), 0, 10),
        'kind'=>$kind, 'name'=>$name, 'source'=>$source, 'line'=>$line,
        'source_sha256'=>hash_file('sha256', $root.'/'.$source),
        'status'=>$status, 'target'=>$target, 'step'=>$step,
        'owner'=>str_contains($kind, 'konveksi') || $kind==='rpc' ? 'pengembang-konveksi' : 'pengembang-marketplace-platform',
        'depends_on'=>$step===5?[1,2,3,4]:[1,2,3,4,5],
        'contract_row'=>$contract,
        'contract_review'=>$contract !== '' ? 'baseline-source; revalidasi runtime per task' : 'wajib ekstrak request/response/role/tabel dari source sebelum implementasi',
        'tables_source_candidates'=>array_keys($tables),
        'input_fields_source_candidates'=>array_values(array_unique($inputMatches[1])),
        'candidate_limit'=>'File-level candidates; not a verified per-action contract. Full contract source retained by path/hash/line.',
        'authorization_baseline'=>$kind==='rpc' ? (in_array($name,['loadSemuaData','getRiwayatBackupJagoLaba','getGeminiApiKeyStatusServerRpc'],true) ? 'Konveksi session/workspace/company; CSRF endpoint' : ($name==='prosesLoginBackend'?'Unauthenticated credential login':'Konveksi session/workspace/company + OWNER|ADMIN; CSRF endpoint; operation gate except document/backup exclusions in dispatch')) : 'See baseline contract and endpoint source; per-action runtime authorization task required',
        'security'=>'Session, workspace, company/store, role dan CSRF sesuai baseline; verifikasi per action, bukan asumsi UI.',
        'test'=>$test,
        'acceptance'=>'Parity request/response, izin dan side effect pada fixture sama; UI/caller aktif; staging smoke; rollback dan pemilik penulis dicatat.',
    ];
};
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root.'/public', FilesystemIterator::SKIP_DOTS));
foreach ($it as $f) if ($f->getExtension()==='php') {
    $name = str_replace('\\', '/', substr($f->getPathname(), strlen($root.'/public/')));
    $add('public', $name, 'public/'.$name, '/'.$name);
}
$rpc = 'app/Modules/Konveksi/Controllers/RpcService.php';
$text = file_get_contents($root.'/'.$rpc);
$start = strpos($text, 'return match($method)'); $end = strpos($text, 'private function toArray', $start);
preg_match_all("/'([^']+)'\\s*(?:,|=>)/", substr($text, $start, $end-$start), $m);
$methods = ['prosesLoginBackend'];
foreach ($m[1] as $name) if (preg_match('/^[A-Za-z][A-Za-z0-9]+$/', $name) && !in_array($name, ['MANUAL','General'], true)) $methods[] = $name;
foreach (array_unique($methods) as $name) $add('rpc', $name, $rpc);
$ajax = 'app/Modules/Marketplace/Controllers/Api/marketplace-ajax.php';
preg_match_all("/case\\s+'([^']+)'\\s*:/", file_get_contents($root.'/'.$ajax), $m);
foreach (array_unique($m[1]) as $name) $add('ajax', $name, $ajax);
$apiDir = $root.'/app/Modules/Marketplace/Controllers/Api';
foreach (glob($apiDir.'/*.php') as $path) {
    if (basename($path)==='marketplace-ajax.php') continue;
    $source = str_replace('\\','/', substr($path, strlen($root)+1));
    $code = file_get_contents($path);
    $names = [];
    preg_match_all('/(?:case\s+|\$action\s*===?\s*)[\'"]([a-z][a-z0-9_]+)[\'"]/', $code, $m);
    $names = array_merge($names, $m[1]);
    preg_match_all('/match\s*\([^\n]*?(?:\$action|\[\s*[\'"]action[\'"]\s*\])[^\n]*?\)\s*\{(.*?)default\s*=>/s', $code, $blocks);
    foreach ($blocks[1] as $block) {
        preg_match_all('/[\'"]([a-z][a-z0-9_]+)[\'"]\s*=>/', $block, $m);
        $names = array_merge($names, $m[1]);
    }
    foreach (array_unique($names) as $name) $add('api-action', basename($path).':'.$name, $source);
}
$base = 'app/Modules/Konveksi/Views/00_Base.html';
preg_match_all("/switchTab\\('([^']+)'\\)/", file_get_contents($root.'/'.$base), $m);
foreach (array_unique($m[1]) as $name) $add('konveksi-menu', $name, $base);
foreach (glob($root.'/app/Modules/Marketplace/Views/*.php') as $path) {
    $add('marketplace-view', basename($path), str_replace('\\','/', substr($path, strlen($root)+1)));
}
foreach (glob($root.'/bin/workers/*.php') as $path) $add('worker', basename($path), 'bin/workers/'.basename($path));
// Frontend control/caller occurrences are captured separately, including both asset trees.
$callers = [];
foreach (['public/assets','app/Modules/Konveksi/Views','app/Modules/Marketplace/Views'] as $dir) {
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root.'/'.$dir, FilesystemIterator::SKIP_DOTS)) as $f) {
        if (!in_array($f->getExtension(), ['php','html','js'], true)) continue;
        foreach (file($f->getPathname()) as $i=>$line) {
            if (preg_match('/fetch\s*\(|google\.script\.run|@click|@submit|<form\b|action=|api\/[a-z-]+\.php/', $line)) {
                $sourceFile=str_replace('\\','/', substr($f->getPathname(), strlen($root)+1));
                $callers[] = ['source'=>$sourceFile, 'line'=>$i+1, 'code'=>trim($line),
                    'task_id'=>'UI-'.substr(hash('sha256',$sourceFile),0,10),
                    'owner'=>str_contains($sourceFile,'Konveksi')||str_contains($sourceFile,'konveksi')?'pengembang-konveksi':'pengembang-marketplace',
                    'status'=>'review caller and rendered control in owning feature task; source occurrence only'];
            }
        }
    }
}
ksort($rows); $rows = array_values($rows);
$out = $root.'/docs/architecture';
file_put_contents($out.'/laravel-feature-checklist-20261101.json', json_encode(['generated_at'=>date(DATE_ATOM),'status'=>'source-inventory; business decisions and runtime contract review pending', 'items'=>$rows,'frontend_controls_and_callers'=>$callers], JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)."\n");
$csv = fopen($out.'/laravel-feature-checklist-20261101.csv','w');
fputcsv($csv, ['id','kind','name','status','step','owner','source','line','target','test','contract_review'], ',', '"', '');
foreach ($rows as $r) fputcsv($csv, array_map(fn($k)=>$r[$k], ['id','kind','name','status','step','owner','source','line','target','test','contract_review']), ',', '"', '');
fclose($csv);
$md = "# Checklist sumber migrasi Laravel — 1 November 2026\n\n";
$md .= "Inventaris source, bukan bukti seluruh fitur lulus. Satu ID menjadi satu task alur bisnis. File JSON menyimpan baris kontrak baseline, kandidat input/tabel, sumber/checksum dan kriteria penerimaan; CSV untuk pelacakan. Keputusan stub legacy masih memerlukan pemilik bisnis.\n\n";
$md .= "| ID | Jenis / fitur | Status implementasi | Step | Source |\n|---|---|---|---|---|\n";
foreach ($rows as $r) $md .= '| '.$r['id'].' | '.$r['kind'].' / `'.$r['name'].'` | '.$r['status'].' | '.$r['step'].' | ['.basename($r['source']).'](../../'.$r['source'].') |'."\n";
$md .= "\nKontrol dan caller frontend: ".count($callers)." kemunculan tersimpan dalam JSON, termasuk dua pohon asset. Angka item mencakup endpoint, action dan halaman sehingga bukan jumlah fitur unik atau persentase selesai. Kontrak per-action yang belum tervalidasi ditandai eksplisit.\n";
file_put_contents($out.'/laravel-feature-checklist-20261101.md',$md);
$counts = array_count_values(array_column($rows,'kind'));
$coverage=['items'=>count($rows),'kinds'=>$counts,'frontend_occurrences'=>count($callers),'frontend_file_tasks'=>count(array_unique(array_column($callers,'task_id'))),'source_hashes_valid'=>true,'unique_task_ids'=>count(array_unique(array_column($rows,'id')))===count($rows),'runtime_parity_claimed'=>false];
foreach($rows as $row) {
    if(!is_file($root.'/'.$row['source']) || hash_file('sha256',$root.'/'.$row['source'])!==$row['source_sha256']) throw new RuntimeException('Source inventory drift');
    foreach(explode(';',$row['test']) as $test) if($test!=='' && !is_file($root.'/laravel-foundation/tests/Feature/'.$test)) throw new RuntimeException('Missing referenced feature test');
}
file_put_contents($out.'/laravel-feature-coverage-20261101.json',json_encode($coverage,JSON_PRETTY_PRINT)."\n");
echo json_encode($coverage, JSON_PRETTY_PRINT)."\n";
