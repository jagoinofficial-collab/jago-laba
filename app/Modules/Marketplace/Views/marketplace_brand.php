<?php
require_once (dirname(__DIR__, 4) . '/app/Shared/Branding/MarketplaceBrandingService.php');
if(!function_exists('jlMarketplaceBrand')){
 function jlMarketplaceBrand(bool $compact=false):string {
  static $config=null;if($config===null)$config=(new MarketplaceBrandingService(Database::pdo()))->read();
  $size=$compact?28:max(36,(int)$config['logo_size']);$fit=htmlspecialchars($config['logo_fit'],ENT_QUOTES,'UTF-8');
  $logo=!empty($config['logo_hash'])?'<img data-jl-logo src="api/marketplace-branding.php?image=1&amp;v='.htmlspecialchars($config['logo_hash'],ENT_QUOTES).'" alt="Logo Jago Laba" style="width:100%;height:100%;object-fit:'.$fit.';border-radius:8px">':'<span class="jl-brand-default" data-jl-logo aria-hidden="true" style="font-weight:900;color:#fbbf24;background:transparent;font-size:13px">JL</span>';
  return '<span class="jl-brand-lockup" data-jl-brand style="display:inline-flex;align-items:center;gap:10px">'
       .'<span class="jl-rotating-logo-frame" style="width:'.$size.'px;height:'.$size.'px;border-radius:12px;flex-shrink:0">'
       .'<span class="jl-rotating-logo-inner" style="border-radius:10px;padding:2px">'.$logo.'</span>'
       .'</span>'
       .'<span class="jl-brand-words"><strong style="font-size:15px;font-weight:900;letter-spacing:-0.4px;color:#172744;line-height:1.2">Jago Laba</strong>'
       .'<small style="display:flex;align-items:center;gap:4px;font-size:8px;font-weight:800;letter-spacing:1px;color:#d97706;margin-top:2px;white-space:nowrap">'
       .'<span style="width:5px;height:5px;border-radius:50%;background:#f59e0b;display:inline-block"></span>MARKETPLACE OS</small></span></span>';
 }
}