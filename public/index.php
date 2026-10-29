<?php
$root=(dirname(__DIR__, 1));
if(!is_file($root.'/storage/install.lock')){header('Location: install.php');exit;}
require_once $root.'/app/Bootstrap/bootstrap.php';
if (!empty($_SESSION['user_id']) && Workspace::active() !== Workspace::KONVEKSI) {
    header('Location: workspace.php'); exit;
}
header('Cache-Control: no-store, private');
$views=$root.'/app/Modules/Konveksi/Views';
function jl_view(string $name): void { global $views; $file=$views.'/'.$name.'.html'; if(!is_file($file)) throw new RuntimeException('View tidak ditemukan: '.$name); readfile($file); }
try{
    jl_view('00_Base');
    foreach(KonveksiModule::viewNames() as $v) jl_view($v);
    echo "\n                </div>\n            </main>\n";
    jl_view('27_UI_Components');
    echo "\n        </template>\n    </div>\n";
    echo '<script>window.JAGO_LABA_LOCAL=true;window.JAGO_LABA_LOCAL_VERSION="ONE-PHASE-2026.09.20";window.JL_SESSION='.json_encode(['authenticated'=>!empty($_SESSION['user_id']),'workspace'=>Workspace::active(),'csrf'=>Workspace::csrf(),'name'=>$_SESSION['name']??''],JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT).';window.jlConfirmWorkspaceSwitch=function(e){if(window.jlUnsavedForm&& !confirm("Ada formulir yang belum disimpan. Ganti ruang kerja?")){e.preventDefault();return false;}return true};window.jlUnsavedForm=false;document.addEventListener("input",function(e){if(e.target.closest("form"))window.jlUnsavedForm=true});document.addEventListener("submit",function(){window.jlUnsavedForm=false});</script>';
    echo '<script src="assets/konveksi/js/jl-api.js?v=20261029"></script>';


    jl_view('28_Core');
}catch(Throwable $e){
    http_response_code(500);
    echo '<!doctype html><meta charset="utf-8"><title>JAGO LABA Boot Error</title><div style="font-family:Inter,Arial;padding:40px;background:#07101f;color:#fff;min-height:100vh"><div style="max-width:900px;margin:auto;background:#111c33;border:1px solid #253657;padding:28px;border-radius:22px"><h1>JAGO LABA LOCAL • BOOT ERROR</h1><p>Aplikasi gagal menyusun frontend.</p><pre style="white-space:pre-wrap;background:#020617;padding:18px;border-radius:12px;color:#fda4af">'.htmlspecialchars($e->getMessage()).'</pre></div></div>';
}
