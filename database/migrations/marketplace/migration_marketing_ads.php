<?php
require_once (dirname(__DIR__, 3) . '/app/Bootstrap/bootstrap.php');
$pdo = Database::pdo();

// 1. Buat Tabel marketplace_product_ads
$pdo->exec("CREATE TABLE IF NOT EXISTS marketplace_product_ads (
    id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    store_id VARCHAR(50) NOT NULL,
    platform ENUM('Shopee', 'TikTok Shop') NOT NULL DEFAULT 'Shopee',
    product_id VARCHAR(60) NULL,
    product_variant_id VARCHAR(60) NULL,
    marketplace_item_id VARCHAR(100) NOT NULL,
    sku VARCHAR(100) NOT NULL,
    product_name VARCHAR(255) NOT NULL,
    image_url VARCHAR(255) NULL,
    ad_spend DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    gross_revenue DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    orders_count INT NOT NULL DEFAULT 0,
    qty_sold INT NOT NULL DEFAULT 0,
    impressions INT NOT NULL DEFAULT 0,
    clicks INT NOT NULL DEFAULT 0,
    conversions INT NOT NULL DEFAULT 0,
    unit_cogs_hpp DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    platform_fee DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    period_start DATE NOT NULL,
    period_end DATE NOT NULL,
    last_synced_at DATETIME NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_store_sku (store_id, sku),
    INDEX idx_platform (platform)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

echo "Tabel marketplace_product_ads berhasil dibuat / diverifikasi.\n";

// 2. Bersihkan dan Seed 21 Produk Marketing Ads persis sesuai gambar referensi FinKami
$pdo->exec("DELETE FROM marketplace_product_ads WHERE store_id = 'STR-SHOPEE-JAGOIN'");

$stmt = $pdo->prepare("INSERT INTO marketplace_product_ads 
    (store_id, platform, marketplace_item_id, sku, product_name, image_url, ad_spend, gross_revenue, orders_count, qty_sold, unit_cogs_hpp, platform_fee, period_start, period_end, last_synced_at)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, '2026-09-22', '2026-10-22', NOW())");

$productsSeed = [
    [
        'id' => '49656881982',
        'sku' => 'RANSEL-BIMO-BLK',
        'name' => 'JAGOIN Tas Ransel Pria Bimo Waterproof Hitam',
        'img' => 'https://images.unsplash.com/photo-1553062407-98eeb64c6a62?w=100&q=80',
        'ad_spend' => 5536275.00,
        'revenue' => 59042888.00,
        'orders' => 452,
        'qty' => 465,
        'hpp' => 39000.00,
        'platform_fee' => 16908114.00
    ],
    [
        'id' => '38237894291',
        'sku' => 'RANSEL-BIMO-NVY',
        'name' => 'JAGOIN Tas Ransel Pria Bimo Waterproof Navy',
        'img' => 'https://images.unsplash.com/photo-1546938576-6e6a64f317cc?w=100&q=80',
        'ad_spend' => 4309391.00,
        'revenue' => 49652363.00,
        'orders' => 601,
        'qty' => 628,
        'hpp' => 45000.00,
        'platform_fee' => 13015909.00
    ],
    [
        'id' => '48473124915',
        'sku' => 'RANSEL-CARRIER-60L',
        'name' => 'JAGOIN Tas Ransel Carrier Gunung Outdoor 60L Cordura',
        'img' => 'https://images.unsplash.com/photo-1577733966973-d680bffd2e80?w=100&q=80',
        'ad_spend' => 3135171.00,
        'revenue' => 24304673.00,
        'orders' => 137,
        'qty' => 140,
        'hpp' => 90000.00,
        'platform_fee' => 6050151.00
    ],
    [
        'id' => '173452888198994558',
        'sku' => 'RANSEL-CORDURA-CASUAL',
        'name' => 'JAGOIN Tas Ransel Pria Casual Cordura Tactical',
        'img' => 'https://images.unsplash.com/photo-1622560480605-d83c853bc5c3?w=100&q=80',
        'ad_spend' => 1726113.00,
        'revenue' => 8854821.00,
        'orders' => 59,
        'qty' => 60,
        'hpp' => 59000.00,
        'platform_fee' => 2627495.00
    ],
    [
        'id' => '27325805853',
        'sku' => 'DOMPET-LOGO-BLK',
        'name' => 'JAGOIN Dompet Pria Hanging Wallet Gantung Leher',
        'img' => 'https://images.unsplash.com/photo-1627123424574-724758594e93?w=100&q=80',
        'ad_spend' => 1708205.00,
        'revenue' => 9788832.00,
        'orders' => 139,
        'qty' => 142,
        'hpp' => 32000.00,
        'platform_fee' => 2741470.00
    ],
    [
        'id' => '43337538223',
        'sku' => 'RANSEL-SEKOLAH-SD',
        'name' => 'JAGOIN Tas Ransel Sekolah Waterproof SD SMP',
        'img' => 'https://images.unsplash.com/photo-1588850561407-ed78c282e89b?w=100&q=80',
        'ad_spend' => 808374.00,
        'revenue' => 6122217.00,
        'orders' => 75,
        'qty' => 76,
        'hpp' => 43689.00,
        'platform_fee' => 1560697.00
    ],
    [
        'id' => '52207270483',
        'sku' => 'VORTEX-TACTICAL',
        'name' => 'JAGOIN Vortex Tas Punggung Tactical Outdoor',
        'img' => 'https://images.unsplash.com/photo-1544816155-12df9643f363?w=100&q=80',
        'ad_spend' => 645063.00,
        'revenue' => 355009.00,
        'orders' => 3,
        'qty' => 3,
        'hpp' => 58000.00,
        'platform_fee' => 87412.00
    ],
    [
        'id' => '44760367431',
        'sku' => 'DOMPET-SEC-GRY',
        'name' => 'JAGOIN Dompet Pria Lipat Waterproof Anti Air',
        'img' => 'https://images.unsplash.com/photo-1512496015851-a90fb38ba796?w=100&q=80',
        'ad_spend' => 601821.00,
        'revenue' => 1620041.00,
        'orders' => 23,
        'qty' => 23,
        'hpp' => 32000.00,
        'platform_fee' => 442155.00
    ],
    [
        'id' => '39201948192',
        'sku' => 'SL-KULIT-BRW',
        'name' => 'JAGOIN Waistbag Tas Selempang Pria Kulit Asli Cokelat',
        'img' => 'https://images.unsplash.com/photo-1548036328-c9fa89d128fa?w=100&q=80',
        'ad_spend' => 540000.00,
        'revenue' => 3420000.00,
        'orders' => 45,
        'qty' => 45,
        'hpp' => 38000.00,
        'platform_fee' => 889200.00
    ],
    [
        'id' => '10293849102',
        'sku' => 'TAS-HP-DRIVER',
        'name' => 'JAGOIN Tas HP Driver Ojol Waterproof Holder Stang',
        'img' => 'https://images.unsplash.com/photo-1584917865442-de89df76afd3?w=100&q=80',
        'ad_spend' => 490000.00,
        'revenue' => 2940000.00,
        'orders' => 88,
        'qty' => 90,
        'hpp' => 18500.00,
        'platform_fee' => 764400.00
    ],
    [
        'id' => '91029384819',
        'sku' => 'WAISTBAG-CORDURA-ARMY',
        'name' => 'JAGOIN Waistbag Tas Bahu Pria Cordura Tactical Army',
        'img' => 'https://images.unsplash.com/photo-1553062407-98eeb64c6a62?w=100&q=80',
        'ad_spend' => 450000.00,
        'revenue' => 2850000.00,
        'orders' => 58,
        'qty' => 60,
        'hpp' => 28000.00,
        'platform_fee' => 741000.00
    ],
    [
        'id' => '82910293848',
        'sku' => 'RANSEL-ROLLTOP-GRY',
        'name' => 'JAGOIN Tas Ransel Rolltop Urban Commuter Abu',
        'img' => 'https://images.unsplash.com/photo-1546938576-6e6a64f317cc?w=100&q=80',
        'ad_spend' => 420000.00,
        'revenue' => 2730000.00,
        'orders' => 32,
        'qty' => 32,
        'hpp' => 48000.00,
        'platform_fee' => 709800.00
    ],
    [
        'id' => '71928301928',
        'sku' => 'SLINGBAG-MINI-CANVAS',
        'name' => 'JAGOIN Slingbag Mini Canvas Casual Unisex',
        'img' => 'https://images.unsplash.com/photo-1548036328-c9fa89d128fa?w=100&q=80',
        'ad_spend' => 380000.00,
        'revenue' => 1950000.00,
        'orders' => 41,
        'qty' => 42,
        'hpp' => 24000.00,
        'platform_fee' => 507000.00
    ],
    [
        'id' => '62918203918',
        'sku' => 'DUFFLE-GYM-WATERPROOF',
        'name' => 'JAGOIN Tas Duffle Gym & Travel Waterproof',
        'img' => 'https://images.unsplash.com/photo-1553062407-98eeb64c6a62?w=100&q=80',
        'ad_spend' => 310000.00,
        'revenue' => 1860000.00,
        'orders' => 19,
        'qty' => 19,
        'hpp' => 52000.00,
        'platform_fee' => 483600.00
    ],
    [
        'id' => '51928301928',
        'sku' => 'POUCH-ORGANIZER-CORDURA',
        'name' => 'JAGOIN Pouch Organizer Kabel & Aksesoris',
        'img' => 'https://images.unsplash.com/photo-1512496015851-a90fb38ba796?w=100&q=80',
        'ad_spend' => 210000.00,
        'revenue' => 1250000.00,
        'orders' => 38,
        'qty' => 40,
        'hpp' => 16000.00,
        'platform_fee' => 325000.00
    ],
    [
        'id' => '41928301928',
        'sku' => 'TOTEBAG-CANVAS-HEAVY',
        'name' => 'JAGOIN Totebag Canvas Premium Sablon DTF',
        'img' => 'https://images.unsplash.com/photo-1544816155-12df9643f363?w=100&q=80',
        'ad_spend' => 180000.00,
        'revenue' => 1020000.00,
        'orders' => 28,
        'qty' => 30,
        'hpp' => 19500.00,
        'platform_fee' => 265200.00
    ],
    [
        'id' => '31928301928',
        'sku' => 'RANSEL-LAPTOP-15',
        'name' => 'JAGOIN Tas Laptop 15 Inch Softcase Busa Tebal',
        'img' => 'https://images.unsplash.com/photo-1588850561407-ed78c282e89b?w=100&q=80',
        'ad_spend' => 145000.00,
        'revenue' => 840000.00,
        'orders' => 15,
        'qty' => 15,
        'hpp' => 31000.00,
        'platform_fee' => 218400.00
    ],
    [
        'id' => '21928301928',
        'sku' => 'TAS-SEPATU-FUTSAL',
        'name' => 'JAGOIN Tas Sepatu Futsal & Olahraga Jaring',
        'img' => 'https://images.unsplash.com/photo-1577733966973-d680bffd2e80?w=100&q=80',
        'ad_spend' => 95000.00,
        'revenue' => 480000.00,
        'orders' => 16,
        'qty' => 16,
        'hpp' => 14000.00,
        'platform_fee' => 124800.00
    ],
    [
        'id' => '11928301928',
        'sku' => 'CARD-HOLDER-SLIM',
        'name' => 'JAGOIN Card Holder Slim Kulit Sintetis 6 Slot',
        'img' => 'https://images.unsplash.com/photo-1627123424574-724758594e93?w=100&q=80',
        'ad_spend' => 65000.00,
        'revenue' => 310000.00,
        'orders' => 18,
        'qty' => 20,
        'hpp' => 8500.00,
        'platform_fee' => 80600.00
    ],
    [
        'id' => '99283019280',
        'sku' => 'GANTUNGAN-KUNCI-PARACORD',
        'name' => 'JAGOIN Gantungan Kunci Tactical Paracord Carabiner',
        'img' => 'https://images.unsplash.com/photo-1622560480605-d83c853bc5c3?w=100&q=80',
        'ad_spend' => 35000.00,
        'revenue' => 140000.00,
        'orders' => 14,
        'qty' => 15,
        'hpp' => 4500.00,
        'platform_fee' => 36400.00
    ],
    [
        'id' => '88283019280',
        'sku' => 'STIKER-PACK-OUTDOOR',
        'name' => 'JAGOIN Sticker Pack Outdoor Waterproof Vinyl',
        'img' => 'https://images.unsplash.com/photo-1512496015851-a90fb38ba796?w=100&q=80',
        'ad_spend' => 14000.00,
        'revenue' => 60000.00,
        'orders' => 6,
        'qty' => 6,
        'hpp' => 3000.00,
        'platform_fee' => 15600.00
    ]
];

$totalSpend = 0;
$totalRev = 0;
$totalOrders = 0;

foreach ($productsSeed as $p) {
    $totalSpend += $p['ad_spend'];
    $totalRev += $p['revenue'];
    $totalOrders += $p['orders'];
    $stmt->execute([
        'STR-SHOPEE-JAGOIN',
        'Shopee',
        $p['id'],
        $p['sku'],
        $p['name'],
        $p['img'],
        $p['ad_spend'],
        $p['revenue'],
        $p['orders'],
        $p['qty'],
        $p['hpp'],
        $p['platform_fee']
    ]);
}

// Juga copy data untuk toko TikTok Shop
$stmtTikTok = $pdo->prepare("INSERT INTO marketplace_product_ads 
    (store_id, platform, marketplace_item_id, sku, product_name, image_url, ad_spend, gross_revenue, orders_count, qty_sold, unit_cogs_hpp, platform_fee, period_start, period_end, last_synced_at)
    SELECT 'STR-TIKTOK-JAGOIN', 'TikTok Shop', marketplace_item_id, sku, product_name, image_url, ad_spend * 0.75, gross_revenue * 0.78, ROUND(orders_count * 0.75), ROUND(qty_sold * 0.75), unit_cogs_hpp, platform_fee * 0.76, period_start, period_end, NOW()
    FROM marketplace_product_ads WHERE store_id = 'STR-SHOPEE-JAGOIN'");
$stmtTikTok->execute();

echo "Seeded " . count($productsSeed) . " produk marketing ads untuk Shopee & TikTok.\n";
echo "Total Iklan: Rp " . number_format($totalSpend, 2) . " | Total Pendapatan: Rp " . number_format($totalRev, 2) . " | ROAS: " . round($totalRev / $totalSpend, 2) . "x\n";
