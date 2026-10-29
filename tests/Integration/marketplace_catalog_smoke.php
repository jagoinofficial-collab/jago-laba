<?php
declare(strict_types=1);
require_once (dirname(__DIR__, 2) . '/app/Bootstrap/bootstrap.php');
require_once (dirname(__DIR__, 2) . '/app/Modules/Marketplace/Services/MarketplaceCatalogService.php');
function catalogCheck(bool $condition,string $message): void { if (!$condition) throw new RuntimeException($message); }
$name = 'jl_catalog_test_' . bin2hex(random_bytes(6));
$server = Database::serverPdo();
$server->exec("CREATE DATABASE `$name` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
$original = Database::config();
$config = $original; $config['db']['name'] = $name; Database::setConfig($config); Database::reset();
try {
    $pdo = Database::pdo();
    $pdo->exec('CREATE TABLE marketplace_stores(id VARCHAR(50) PRIMARY KEY,company_key VARCHAR(120),store_name VARCHAR(100),platform VARCHAR(50),archived_at DATETIME NULL)');
    $pdo->exec("INSERT INTO marketplace_stores VALUES ('S','A','Toko Shopee','Shopee',NULL),('T','A','Toko TikTok','TikTok Shop',NULL),('X','B','Toko Lain','Shopee',NULL)");
    $pdo->exec('CREATE TABLE marketplace_orders(id VARCHAR(100) PRIMARY KEY,store_id VARCHAR(50),order_date DATETIME,order_status VARCHAR(50),internal_state VARCHAR(20),total_cogs_hpp DECIMAL(15,2),net_margin_profit DECIMAL(15,2),net_settlement_amount DECIMAL(15,2),transaction_detail_json LONGTEXT NULL)');
    $pdo->exec('CREATE TABLE marketplace_order_items(id INT PRIMARY KEY,order_id VARCHAR(100),sku VARCHAR(100),item_name VARCHAR(255),qty INT,selling_price DECIMAL(15,2),unit_cogs_hpp DECIMAL(15,2),subtotal_cogs DECIMAL(15,2))');
    $pdo->exec('CREATE TABLE marketplace_sku_mappings(store_id VARCHAR(50),marketplace_sku VARCHAR(100),marketplace_product_name VARCHAR(255))');
    $pdo->exec('CREATE TABLE marketplace_hpp_history(id INT AUTO_INCREMENT PRIMARY KEY,store_id VARCHAR(50),marketplace_sku VARCHAR(100),unit_hpp DECIMAL(15,2),effective_from DATE,source VARCHAR(80),reason VARCHAR(255),changed_by VARCHAR(120),UNIQUE KEY(store_id,marketplace_sku,effective_from))');
    $pdo->exec('CREATE TABLE marketplace_product_ads(store_id VARCHAR(50),sku VARCHAR(100),unit_cogs_hpp DECIMAL(15,2))');
    require (dirname(__DIR__, 2) . '/database/migrations/marketplace/migration_marketplace_catalog.php');
    require (dirname(__DIR__, 2) . '/database/migrations/marketplace/migration_marketplace_catalog.php');
    $pdo->exec("INSERT INTO marketplace_catalog_variants(store_id,external_product_id,external_variant_id,product_name,sku,variant_name,price,original_price,stock_qty,source_status,source_updated_at) VALUES ('S','P','V1','Tas <script>test</script>','SKU-A','Hitam',125494,225000,10,'Aktif',NOW()),('S','P','V2','Tas <script>test</script>','SKU-B','Biru',130000,225000,5,'Aktif',NOW()),('T','TP','TV','Tas TikTok','SKU-A','Hitam',100000,NULL,NULL,'Aktif',NOW())");
    $snapshot = json_encode(['items'=>[['sku'=>'SKU-A','quantity'=>1,'returned_quantity'=>0,'total_cogs'=>59000,'unit_cogs'=>59000]],'total_cogs'=>59000,'net_income'=>94459]);
    $stmt=$pdo->prepare("INSERT INTO marketplace_orders VALUES ('O','S',NOW(),'Terkirim','active',59000,35459,94459,?),('OLD','S',DATE_SUB(NOW(),INTERVAL 30 DAY),'Selesai','active',59000,35459,94459,NULL),('OTHER','X',NOW(),'Selesai','active',59000,35459,94459,NULL),('CANCEL','S',NOW(),'Dibatalkan','active',0,0,0,NULL)"); $stmt->execute([$snapshot]);
    $pdo->exec("INSERT INTO marketplace_order_items VALUES (1,'O','SKU-A','Tas',1,125494,59000,59000),(2,'OLD','SKU-A','Tas',1,125494,59000,59000),(3,'OTHER','SKU-A','Tas',1,125494,59000,59000),(4,'CANCEL','SKU-B','Tas',9,130000,0,0)");
    $pdo->exec("INSERT INTO marketplace_product_ads VALUES ('S','SKU-A',59000),('T','SKU-A',40000)");
    $_SESSION=['company_key'=>'A','user_id'=>'OWNER'];
    $catalog=new MarketplaceCatalogService($pdo);
    $catalog->saveVariant('T',['external_product_id'=>'TP','external_variant_id'=>'TV','sku'=>'SKU-A','product_name'=>'Tas TikTok','source_updated_at'=>'2026-01-01 00:00:00','price'=>1]);
    catalogCheck((float)$pdo->query("SELECT price FROM marketplace_catalog_variants WHERE store_id='T'")->fetchColumn()===100000.0,'stale catalog replaced current price');
    $data=$catalog->listing();
    catalogCheck(count($data['products'])===2 && $data['sold']===1.0,'catalog grouping, 14-day sales or cancellation filter failed');
    catalogCheck(count($data['products'][0]['variants'])===2,'variants not grouped');
    catalogCheck(count($catalog->listing(['platform'=>'TikTok Shop'])['products'])===1,'platform filter failed');
    catalogCheck($catalog->updateHpp('S','SKU-A','60000',true)===2,'historical orders not updated');
    $order=$pdo->query("SELECT * FROM marketplace_orders WHERE id='O'")->fetch(PDO::FETCH_ASSOC);
    catalogCheck((float)$order['total_cogs_hpp']===60000.0 && (float)$order['net_margin_profit']===34459.0,'transaction totals not recalculated');
    catalogCheck(json_decode($order['transaction_detail_json'],true)['total_cogs']===60000,'detail snapshot stale');
    catalogCheck((float)$pdo->query("SELECT total_cogs_hpp FROM marketplace_orders WHERE id='OTHER'")->fetchColumn()===59000.0,'other store changed');
    catalogCheck((float)$pdo->query("SELECT unit_cogs_hpp FROM marketplace_product_ads WHERE store_id='S'")->fetchColumn()===60000.0,'marketing HPP stale');
    catalogCheck((int)$pdo->query('SELECT COUNT(*) FROM marketplace_hpp_changes')->fetchColumn()===1,'audit not saved');
    catalogCheck($catalog->updateHpp('S','SKU-A','65000')===1,'default HPP edit rewrote historical orders');
    catalogCheck((float)$pdo->query("SELECT total_cogs_hpp FROM marketplace_orders WHERE id='OLD'")->fetchColumn()===60000.0,'old HPP changed without consent');
    catalogCheck($catalog->updateHpp('S','SKU-A','70000',false,date('Y-m-d',strtotime('+7 days')))===0,'future HPP applied before effective date');
    catalogCheck((float)$pdo->query("SELECT unit_cogs_hpp FROM marketplace_product_ads WHERE store_id='S'")->fetchColumn()===65000.0,'future HPP changed current marketing cost');
    try {$catalog->updateHpp('X','SKU-A','1');throw new RuntimeException('foreign store allowed');}catch(DomainException $expected){}
    try {$catalog->updateHpp('S','MISSING','1');throw new RuntimeException('unknown SKU allowed');}catch(DomainException $expected){}
    try {$catalog->updateHpp('S','SKU-A','-1');throw new RuntimeException('negative HPP allowed');}catch(InvalidArgumentException $expected){}
    $catalogData=$catalog->listing();$globalFilters=['date_start'=>'','date_end'=>''];$productSection='hpp';
    $query=static fn(array $changes=[]):string=>http_build_query($changes);
    if (!function_exists('formatRp')) {function formatRp($value):string{return 'Rp '.number_format((float)$value,0,',','.');}}
    ob_start();require (dirname(__DIR__, 2) . '/app/Modules/Marketplace/Views/marketplace_global_products.php');$html=ob_get_clean();
    catalogCheck(str_contains($html,'data-hpp-mode="product"')&&str_contains($html,'data-hpp-mode="sku"')&&str_contains($html,'data-hpp-all'),'product HPP selection missing');
    catalogCheck(str_contains($html,'Harga & Diskon') && str_contains($html,'data-edit-global-hpp') && str_contains($html,'&lt;script&gt;test&lt;/script&gt;') && !str_contains($html,'<script>test</script>'),'product view or escaping failed');
    $pdo->exec("INSERT INTO marketplace_order_items VALUES (5,'O','TRANSACTION-ONLY','Bukan katalog',1,100,50,50)");
    $pdo->exec("INSERT INTO marketplace_sku_mappings VALUES ('T','MAPPING-ONLY','Belum ada produk API')");
    $storeCatalog=$catalog->listingForStore('S',['store_id'=>'T','platform'=>'TikTok Shop','official_only'=>false]);
    catalogCheck(count($storeCatalog['products'])===1&&$storeCatalog['products'][0]['store_id']==='S'&&$storeCatalog['products'][0]['platform']==='Shopee','store filters allowed another store/platform');
    catalogCheck(count($storeCatalog['products'][0]['variants'])===2,'observed transaction product leaked into API-only store catalog');
    $tiktok=$catalog->listingForStore('T');catalogCheck(count($tiktok['products'])===1&&$tiktok['products'][0]['platform']==='TikTok Shop','TikTok catalog includes mapping or foreign products');
    try{$catalog->listingForStore('X');throw new RuntimeException('foreign company catalog allowed');}catch(DomainException $expected){}
    $activeStoreId='S';$activeStore=['id'=>'S','platform'=>'Shopee','store_name'=>'Toko Shopee','is_connection_verified'=>false];$_GET=[];
    ob_start();require (dirname(__DIR__, 2) . '/app/Modules/Marketplace/Views/marketplace_store_products.php');$storeHtml=ob_get_clean();
    catalogCheck(str_contains($storeHtml,'Produk Toko')&&str_contains($storeHtml,'product_search')&&str_contains($storeHtml,'Buka Integrasi API')&&!str_contains($storeHtml,'Toko TikTok'),'store view does not respect selected shop');
    catalogCheck(str_contains($storeHtml,'store=S')&&!str_contains($storeHtml,'scope=global&amp;tab=produk'),'product sub-tabs left store context');
    $pdo->exec("INSERT INTO marketplace_catalog_variants(store_id,external_product_id,external_variant_id,product_name,sku,source_status,source_updated_at) VALUES ('S','DEAD','DEAD','Produk Dihapus','DEAD-SKU','DELETED',NOW()),('S','OFF','OFF','Produk Nonaktif','OFF-SKU','SELLER_DEACTIVATED',NOW())");
    catalogCheck(count($catalog->listingForStore('S')['products'])===1,'inactive/deleted products shown');
    $catalog->updateProductHpp('S','P','62000',false,date('Y-m-d'));$h=json_decode($pdo->query("SELECT transaction_detail_json FROM marketplace_orders WHERE id='O'")->fetchColumn(),true);catalogCheck((float)$h['total_cogs']===62000.0&&(float)$pdo->query("SELECT net_margin_profit FROM marketplace_orders WHERE id='O'")->fetchColumn()===32459.0,'product HPP did not update real net profit');
    try{$catalog->updateProductHpp('X','P','1');throw new RuntimeException('foreign product batch allowed');}catch(DomainException $expected){}
    $batch=$catalog->updateHppBatch([['store_id'=>'S','sku'=>'SKU-A','hpp'=>'63000'],['store_id'=>'S','sku'=>'SKU-B','hpp'=>'64000']]);catalogCheck($batch['sku_count']===2,'HPP batch SKU count wrong');catalogCheck((float)$pdo->query("SELECT net_margin_profit FROM marketplace_orders WHERE id='O'")->fetchColumn()===31459.0,'batch did not synchronize transaction profit');
    try{$catalog->updateHppBatch([['store_id'=>'S','sku'=>'SKU-A','hpp'=>'70000'],['store_id'=>'X','sku'=>'SKU-A','hpp'=>'80000']]);throw new RuntimeException('foreign batch permitted');}catch(DomainException $expected){}catalogCheck((float)$pdo->query("SELECT net_margin_profit FROM marketplace_orders WHERE id='O'")->fetchColumn()===31459.0,'invalid batch partially committed');
    try{$catalog->updateHppBatch([['store_id'=>'S','sku'=>'SKU-A','hpp'=>'70000'],['store_id'=>'S','sku'=>'SKU-B','hpp'=>'-1']]);throw new RuntimeException('invalid amount permitted');}catch(InvalidArgumentException $expected){}
    echo "PASS: catalog grouping, variants, 14-day sales, platform filtering, historical HPP propagation, snapshot/marketing consistency, audit and store isolation\n";
} finally {
    Database::reset(); Database::setConfig($original);
    $server->exec("DROP DATABASE `$name`");
}
