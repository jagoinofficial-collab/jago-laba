<?php
declare(strict_types=1);
require __DIR__.'/../../app/Modules/Marketplace/Integrations/Support/MarketplaceIntegrationException.php';
require __DIR__.'/../../app/Modules/Marketplace/Integrations/Support/MarketplaceApiOrigin.php';
require __DIR__.'/../../app/Modules/Marketplace/Services/MarketplaceIntegrationPolicy.php';
foreach(['http://open-api.tiktokglobalshop.com','https://open-api.tiktokglobalshop.com.evil.test','https://user@open-api.tiktokglobalshop.com','https://open-api.tiktokglobalshop.com:8443','https://open-api.tiktokglobalshop.com/path','https://evil.test'] as $url){
    $blocked=false;try{MarketplaceApiOrigin::validate('TikTok Shop',$url);}catch(MarketplaceIntegrationException){$blocked=true;}
    if(!$blocked)throw new RuntimeException('Unsafe API origin accepted.');
}
MarketplaceApiOrigin::validate('TikTok Shop','https://open-api.tiktokglobalshop.com');
MarketplaceApiOrigin::validate('Shopee','https://partner.shopeemobile.com');
foreach(['error_permission'=>'permission','error_auth'=>'authorization','error_param'=>'request','error_server'=>'temporary','error_rate_limit'=>'rate_limit'] as $native=>$category){
    try{MarketplaceApiOrigin::shopeeError($native);}catch(MarketplaceIntegrationException $e){
        if(MarketplaceIntegrationPolicy::classify($e->errorCode)!==$category)throw new RuntimeException('Incorrect native API failure classification: '.$native);
    }
}
echo "PASS: API host allowlist and native Shopee error classification.\n";
