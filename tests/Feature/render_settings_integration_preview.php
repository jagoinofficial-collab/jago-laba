<?php
require_once (dirname(__DIR__, 2) . '/app/Bootstrap/bootstrap.php');
$_GET['peng_sub']='tambah_toko';$globalSettings=true;$partnerVaultReady=true;$canManageStoreArchive=true;$archivedStores=[['id'=>'PREVIEW-ARCHIVED','store_name'=>'Toko Arsip Uji','platform'=>'TikTok Shop','archived_at'=>'2026-10-27 09:00:00','environment_label'=>'Sandbox']];
require_once dirname(__DIR__,2).'/app/Modules/Marketplace/Services/MarketplaceIntegrationPolicy.php';
$integrationHealth=new class {
 public function forStore(string $id):array {
  $resources=[];foreach(MarketplaceIntegrationPolicy::resources('TikTok Shop') as $key=>$definition){$state=$definition['supported']?'unverified':'unsupported';if($key==='products'||$key==='prices'||$key==='stock')$state='available';if($key==='finance')$state='permission';$resources[]=['key'=>$key,'label'=>$definition['label'],'state'=>$state,'state_label'=>['available'=>'Tersedia','unverified'=>'Belum diverifikasi','unsupported'=>'Tidak didukung konektor','permission'=>'Memerlukan izin'][$state],'last_verified_at'=>$state==='available'?'2026-10-27 09:00:00':null,'last_attempt_at'=>null,'error_code'=>$key==='finance'?'TIKTOK_API_105005':null,'sync_status'=>'not_started','sync_label'=>'Belum dijalankan','processed'=>0,'retry_at'=>null,'retry_attempt'=>0,'retry_job_id'=>null];}
  return ['store_id'=>$id,'platform'=>'TikTok Shop','store_name'=>'SANDBOX Â· Toko Pengembangan Preview','connection_status'=>'verified','connection_label'=>'Terhubung','environment'=>'development','environment_label'=>'Development Shop','last_successful_sync_at'=>'2026-10-27 09:00:00','counts'=>['products'=>24,'variants'=>86],'resources'=>$resources,'can_manage'=>true,'metadata_ready'=>true];
 }
};
$autoSyncService=new class{public function available():bool{return true;}public function states(string $store):array{return [];} };
$stores=[['id'=>'PREVIEW-SANDBOX','store_name'=>'SANDBOX Â· Toko Pengembangan Preview','platform'=>'TikTok Shop','is_connection_verified'=>true,'oauth_connection_id'=>1,'oauth_status'=>'verified','oauth_external_store_id'=>'SHOP_PREVIEW','sync_label'=>'Sinkronisasi berhasil','last_successful_sync_at'=>'2026-10-25 20:30:00']];
$oauthConfig=['is_admin'=>true,'callback_urls'=>['Shopee'=>'https://example.test/api/callback?platform=Shopee','TikTok Shop'=>'https://example.test/api/callback?platform=TikTok'],'platforms'=>['Shopee'=>['ready'=>false],'TikTok Shop'=>['ready'=>true]]];
$partnerConfigStatus=['Shopee'=>['partner_id'=>'123456','has_secret'=>true,'mode'=>'sandbox','last_test_status'=>'failed','last_tested_at'=>'2026-10-25 20:00:00','partner_key'=>'SECRET_MUST_NOT_RENDER'],'TikTok Shop'=>['app_key'=>'APP_PREVIEW','service_id'=>'SERVICE_PREVIEW','has_secret'=>true,'last_test_status'=>'passed','last_tested_at'=>'2026-10-25 20:30:00','app_secret'=>'SECRET_MUST_NOT_RENDER']];
ob_start();require (dirname(__DIR__, 2) . '/app/Modules/Marketplace/Views/marketplace_integration_page.php');$html=ob_get_clean();
if(str_contains($html,'SECRET_MUST_NOT_RENDER')||!str_contains($html,'peng_sub=integrasi')||!str_contains($html,'data-settings-connect')||!str_contains($html,'Akun iklan')||str_contains($html,'Pengaturan Aplikasi'))throw new RuntimeException('Integration settings render failed');
$html=str_replace('<head>','<head><base href="/jago-laba/public/">',$html);
$previewDir=dirname(__DIR__,2).'/storage/temporary/integration-qa';
if(!is_dir($previewDir))mkdir($previewDir,0700,true);
file_put_contents($previewDir.'/preview.html',$html);
$_GET['peng_sub']='integrasi';ob_start();require (dirname(__DIR__, 2) . '/app/Modules/Marketplace/Views/marketplace_integration_page.php');$integrationHtml=ob_get_clean();
if(str_contains($integrationHtml,'data-settings-connect')||str_contains($integrationHtml,'SECRET_MUST_NOT_RENDER')||!str_contains($integrationHtml,'tiktok-ads-config'))throw new RuntimeException('Credential configuration must not start shop authorization');
if(str_contains($html,'data-shopee-ads-connect')||str_contains($html,'+ Hubungkan Shopee Ads')||!str_contains($html,'data-capability-grid'))throw new RuntimeException('Unsupported Ads connector shown or capability matrix missing');
if(!str_contains($html,'data-auto-mode="status"')||!str_contains($html,'data-auto-mode="new"')||!str_contains($html,'data-auto-mode="unpaid"'))throw new RuntimeException('Auto sync switches missing');
if(!str_contains($html,'data-settings-disconnect')||!str_contains($html,'Development Shop'))throw new RuntimeException('Connection controls missing');
echo "PASS: settings layout, integration navigation, connect controls, scoped status and secret masking. Preview uses fixture data.\n";
