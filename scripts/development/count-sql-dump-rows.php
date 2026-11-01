<?php
declare(strict_types=1);

// phpMyAdmin dump scanner: quoted semicolons/parentheses never delimit rows.
function dumpCounts(string $path): array
{
    $sql=file_get_contents($path); $n=strlen($sql); $start=0; $quote=null; $lineComment=false; $blockComment=false; $tables=[];
    $consume=function(string $statement) use (&$tables):void {
        $statement=preg_replace('/\A(?:\s|--[^\n]*(?:\n|$)|\/\*.*?\*\/)+/s','',$statement);
        if(preg_match('/^CREATE TABLE\s+`([^`]+)`/i',$statement,$m)) { $tables[$m[1]] ??= 0; return; }
        if(!preg_match('/^INSERT INTO\s+`([^`]+)`\s*\([^;]*?\)\s*VALUES\s*/is',$statement,$m)) return;
        $table=$m[1]; $tail=substr($statement,strlen($m[0])); $q=null; $depth=0; $count=0;
        for($i=0,$len=strlen($tail);$i<$len;$i++) {
            $c=$tail[$i];
            if($q!==null) {
                if($c==='\\') { $i++; continue; }
                if($c===$q) { if(($tail[$i+1]??'')===$q) $i++; else $q=null; }
                continue;
            }
            if($c==="'" || $c==='"' || $c==='`') { $q=$c; continue; }
            if($c==='(') { if($depth===0) $count++; $depth++; }
            elseif($c===')') $depth--;
        }
        if($q!==null || $depth!==0) throw new RuntimeException('Malformed insert in dump');
        $tables[$table]=($tables[$table]??0)+$count;
    };
    for($i=0;$i<$n;$i++) {
        $c=$sql[$i]; $next=$sql[$i+1]??'';
        if($lineComment) { if($c==="\n") $lineComment=false; continue; }
        if($blockComment) { if($c==='*' && $next==='/') { $blockComment=false; $i++; } continue; }
        if($quote!==null) {
            if($c==='\\') { $i++; continue; }
            if($c===$quote) { if($next===$quote) $i++; else $quote=null; } continue;
        }
        if($c==='-' && $next==='-' && ctype_space($sql[$i+2]??' ')) { $lineComment=true; continue; }
        if($c==='/' && $next==='*') { $blockComment=true; $i++; continue; }
        if($c==="'" || $c==='"' || $c==='`') { $quote=$c; continue; }
        if($c===';') { $consume(substr($sql,$start,$i-$start)); $start=$i+1; }
    }
    if($quote!==null || $blockComment) throw new RuntimeException('Unterminated dump token');
    ksort($tables); return $tables;
}

if(realpath($_SERVER['SCRIPT_FILENAME']??'')===__FILE__) {
    $stage='D:/JAGO_LABA_STAGING/laravel-step1-4-20261101';
    $audit=json_decode(file_get_contents($stage.'/evidence/data-audit.json'),true,512,JSON_THROW_ON_ERROR);
    $result=[];
    foreach(['jl_s14_market'=>'jago_laba.sql','jl_s14_konveksi'=>'jago_laba_konveksi.sql'] as $schema=>$dump) {
        $counts=dumpCounts($stage.'/backup/'.$dump);
        $actual=$audit['schemas'][$schema]['row_counts']; ksort($actual);
        $result[$schema]=['source_sha256'=>hash_file('sha256',$stage.'/backup/'.$dump),'source_tables'=>count($counts),'source_rows'=>array_sum($counts),'all_table_counts_match'=>$counts===$actual,'source_counts'=>$counts];
        if($counts!==$actual) throw new RuntimeException('Dump-vs-restored row counts differ: '.$schema);
    }
    file_put_contents($stage.'/evidence/dump-row-parity.json',json_encode($result,JSON_PRETTY_PRINT)."\n");
    echo "Every table row count matches its exact-hash source dump in both databases.\n";
}
