<?php
require_once (dirname(__DIR__, 2) . '/app/Modules/Marketplace/Services/MarketplaceOAuthOrigin.php');
$callback='https://example.ngrok-free.dev/jago-laba/public/api/marketplace-oauth.php?platform=Shopee';
foreach ([['https://example.ngrok-free.dev',true],['https://example.ngrok-free.dev:443',true],['http://localhost',false],['http://example.ngrok-free.dev',false],['https://other.ngrok-free.dev',false],['https://example.ngrok-free.dev:444',false],['null',false]] as [$origin,$expected]) {
    if(MarketplaceOAuthOrigin::matches($callback,$origin)!==$expected)throw new RuntimeException('Unexpected OAuth origin result');
}
echo "PASS: callback origin, localhost mismatch, scheme/port checks and invalid origins\n";
