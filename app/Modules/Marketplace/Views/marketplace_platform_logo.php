<?php
declare(strict_types=1);

/** Platform identity from the connected store, independent of the app logo. */
function jlMarketplacePlatformLogo(string $platform, int $size=32): string
{
    $size=in_array($size,[24,32,40,48],true)?$size:32;
    $key=strtolower(preg_replace('/[^a-z0-9]/i','',$platform));
    $asset=match($key){'shopee'=>'shopee','tiktok','tiktokshop'=>'tiktok',default=>null};
    $label=htmlspecialchars($platform,ENT_QUOTES,'UTF-8');
    if($asset===null)return '<span class="jl-platform-logo jl-platform-logo-fallback" style="width:'.$size.'px;height:'.$size.'px" aria-label="'.$label.'">'.htmlspecialchars(strtoupper(substr($platform,0,2)),ENT_QUOTES,'UTF-8').'</span>';
    return '<img class="jl-platform-logo" src="assets/marketplace/images/marketplaces/'.$asset.'.png" width="'.$size.'" height="'.$size.'" style="width:'.$size.'px;height:'.$size.'px" alt="Logo '.$label.'" decoding="async">';
}
