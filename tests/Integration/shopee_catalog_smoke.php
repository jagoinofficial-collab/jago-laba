<?php
declare(strict_types=1);
require dirname(__DIR__, 2) . '/app/bootstrap.php';
MarketplaceModule::boot();
require_once dirname(__DIR__, 2) . '/app/Modules/Marketplace/Services/MarketplaceSyncService.php';
function catalogAssert(bool $ok, string $message): void { if (!$ok) throw new RuntimeException($message); }
$settings = ['partner_id'=>'123','partner_key'=>'FIXTURE-SECRET','api_base_url'=>'https://partner.shopeemobile.com'];
$requests = []; $fail = false; $loop = false; $incomplete = false;
$transport = function($method, $url) use (&$requests, &$fail, &$loop, &$incomplete) {
    parse_str(parse_url($url, PHP_URL_QUERY), $q); $path = parse_url($url, PHP_URL_PATH);
    catalogAssert($method === 'GET', 'Catalog must only read marketplace data.');
    catalogAssert(hash_equals(hash_hmac('sha256','123'.$path.$q['timestamp'].'TOKEN'.'555','FIXTURE-SECRET'),$q['sign']), 'Bad shop signature.');
    $requests[] = $path;
    if ($path === '/api/v2/product/get_item_list') {
        catalogAssert($q['item_status']==='NORMAL' && !isset($q['update_time_from']), 'Catalog must include all active items, not just recent orders.');
        if ((int)$q['offset'] > 0 && $fail) return ['error'=>'error_permission','message'=>'fixture'];
        return ['response'=> (int)$q['offset'] === 0
            ? ['item'=>[['item_id'=>100]],'has_next_page'=>true,'next'=>$loop?0:20]
            : ['item'=>[['item_id'=>200]],'has_next_page'=>false]];
    }
    if ($path === '/api/v2/product/get_item_base_info') {
        $id=(string)$q['item_id_list'];
        $item=['item_id'=>$id,'item_name'=>'Produk '.$id,'item_sku'=>'PARENT-'.$id,'has_model'=>$id==='100','item_status'=>'NORMAL','update_time'=>time(),'image'=>['image_url_list'=>['https://example.com/product.png']]];
        if ($id==='200') $item+=['price_info'=>[['currency'=>'IDR','current_price'=>0,'original_price'=>50000]],'stock_info_v2'=>['summary_info'=>['total_available_stock'=>0]]];
        return ['response'=>['item_list'=>[$item]]];
    }
    if ($path === '/api/v2/product/get_model_list') return ['response'=>$incomplete?[]:['model'=>[
        ['model_id'=>11,'model_status'=>'MODEL_NORMAL','model_sku'=>'BLACK','model_name'=>'Hitam','price_info'=>[['currency'=>'IDR','current_price'=>85000,'original_price'=>100000]],'stock_info_v2'=>['summary_info'=>['total_available_stock'=>7],'seller_stock'=>[['stock'=>99]]]],
        ['model_id'=>12,'model_sku'=>'GREY','tier_index'=>[0],'price_info'=>[['currency'=>'IDR','current_price'=>90000,'original_price'=>110000]]],
        ['model_id'=>13,'model_status'=>'UNAVAILABLE','model_sku'=>'HIDDEN'],
    ],'tier_variation'=>[['option_list'=>[['option'=>'Abu Abu']]]]]];
    throw new RuntimeException('Unexpected catalog endpoint.');
};
$client = new ShopeeCatalogDataClient($settings,'555',new MarketplaceHttpClient(),$transport);
$first=$client->call('POST','/product/202309/products/search',[],'TOKEN');catalogAssert($first['next_page_token']==='20','Offset not retained.');
$product=$client->call('GET','/product/202309/products/100',[],'TOKEN');
catalogAssert(count($product['skus'])===2 && $product['skus'][0]['id']==='100:11','Model identity or active filtering.');
catalogAssert($product['skus'][0]['inventory'][0]['quantity']===7.0,'Stock summed twice.');
catalogAssert($product['skus'][1]['inventory']===[] && $product['skus'][1]['sales_attributes'][0]['value_name']==='Abu Abu','Unknown stock or tier name changed.');
$single=$client->call('GET','/product/202309/products/200',[],'TOKEN');catalogAssert($single['skus'][0]['id']==='200:0' && $single['skus'][0]['price']['sale_price']===0.0,'Single product or real zero lost.');
foreach (['loop','incomplete'] as $case) {
    $$case=true; $blocked=false;
    try { $client->call('GET',$case==='loop'?'/product/202309/products/search':'/product/202309/products/100',[],'TOKEN'); }
    catch (MarketplaceIntegrationException $e) { $blocked=true; }
    $$case=false; catalogAssert($blocked,'Invalid response was accepted: '.$case);
}
$blocked=false;try{(new ShopeeCatalogDataClient($settings+[],'555',new MarketplaceHttpClient(),$transport))->call('GET','/write',[],'TOKEN');}catch(MarketplaceIntegrationException $e){$blocked=true;}catalogAssert($blocked,'Write endpoint accepted.');
$original=Database::config();$source=$original['db']['name'];catalogAssert((bool)preg_match('/^[A-Za-z0-9_]+$/',$source),'Unsafe fixture source.');
$name='jl_shopee_catalog_test_'.bin2hex(random_bytes(5));$server=Database::serverPdo();$server->exec("CREATE DATABASE `$name`");
$config=$original;$config['db']['name']=$name;Database::setConfig($config);Database::reset();
try {
    $p=Database::pdo();foreach(['marketplace_stores','marketplace_oauth_connections','marketplace_sync_jobs','marketplace_sync_records','marketplace_partner_settings','marketplace_catalog_variants','marketplace_orders','marketplace_order_items','marketplace_hpp_history','marketplace_sku_mappings'] as $table)$p->exec("CREATE TABLE `$table` LIKE `$source`.`$table`");
    MarketplaceIncrementalSync::install($p);
    $_SESSION=['user_id'=>'FIXTURE','role'=>'OWNER','workspace'=>'marketplace','company_key'=>'CATALOG-A'];
    $p->exec("INSERT INTO marketplace_stores(id,company_key,platform,store_name,store_identifier,authorization_status) VALUES ('S','CATALOG-A','Shopee','Shopee fixture','555','verified'),('X','OTHER','Shopee','Other fixture','666','verified')");
    $p->prepare("INSERT INTO marketplace_oauth_connections(company_key,store_id,platform,external_store_id,external_store_name,access_token_encrypted,authorized_at,verified_at,status) VALUES ('CATALOG-A','S','Shopee','555','Shopee fixture',?,NOW(),NOW(),'verified')")->execute([MarketplaceTokenVault::encrypt('TOKEN')]);
    $p->exec("INSERT INTO marketplace_hpp_history(store_id,marketplace_sku,unit_hpp,effective_from) VALUES ('S','BLACK',59000,'2020-01-01')");
    $sync=new MarketplaceSyncService($p,fn($cfg)=>$client,true,true,true);
    $run=$sync->enqueueCatalog('S');catalogAssert($sync->enqueueCatalog('S')===$run,'Duplicate catalog job.');
    for($i=0;$i<8;$i++)$state=$sync->step('S',$run);
    catalogAssert($state['jobs'][0]['status']==='succeeded','Shopee catalog job did not finish.');
    $catalog=(new MarketplaceCatalogService($p))->listingForStore('S');catalogAssert(count($catalog['products'])===2,'Products absent from store listing.');
    $rows=$p->query('SELECT * FROM marketplace_catalog_variants')->fetchAll(PDO::FETCH_ASSOC);$by=array_column($rows,null,'external_variant_id');
    catalogAssert((float)$by['100:11']['price']===85000.0 && (float)$by['100:11']['original_price']===100000.0 && $by['100:11']['source_status']==='NORMAL','Catalog price/status mapping.');
    catalogAssert((float)$p->query("SELECT unit_hpp FROM marketplace_hpp_history WHERE marketplace_sku='BLACK'")->fetchColumn()===59000.0,'Sync overwrote HPP.');
    $blocked=false;try{$sync->enqueueCatalog('X');}catch(DomainException $e){$blocked=true;}catalogAssert($blocked,'Company isolation broken.');
    // Failure halfway through a scan must not reconcile unseen products as deleted.
    $p->exec("UPDATE marketplace_sync_checkpoints SET completed_until=completed_until-86401 WHERE dataset='products'");
    $fail=true;$run=$sync->enqueueCatalog('S');for($i=0;$i<4;$i++)$state=$sync->step('S',$run);
    catalogAssert($state['jobs'][0]['status']==='failed','Permission failure marked success.');
    catalogAssert((int)$p->query("SELECT COUNT(*) FROM marketplace_catalog_variants WHERE external_product_id='200' AND catalog_visible=1")->fetchColumn()===1,'Failed scan hid last successful catalog.');
    $manual=new MarketplaceSyncService($p,fn($cfg)=>$client,false,false,true);$all=$manual->enqueueAll('S');
    catalogAssert(array_column($all['jobs'],'job_type')===['products','orders','transactions'],'Shopee sync omitted a supported dataset.');
    echo "PASS Shopee catalog: signed read-only API, pagination, item/model identity, prices, zero/unknown stock, active variants, store listing, HPP preservation, failure safety and tenant isolation.\n";
} finally { Database::setConfig($original);Database::reset();$server->exec("DROP DATABASE `$name`"); }
