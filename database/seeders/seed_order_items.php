<?php
require_once (dirname(__DIR__, 2) . '/app/Bootstrap/bootstrap.php');
$pdo = Database::pdo();

// Cek order yang belum punya item
$orders = $pdo->query("SELECT id, store_id, gross_sales, total_cogs_hpp, buyer_username FROM marketplace_orders")->fetchAll(PDO::FETCH_ASSOC);

$sampleItems = [
    ['sku' => 'RANSEL-BIMO-BLK', 'name' => 'JAGOIN Tas Ransel Pria Bimo Waterproof Hitam', 'hpp' => 45000, 'price' => 79000],
    ['sku' => 'RANSEL-BIMO-NVY', 'name' => 'JAGOIN Tas Ransel Pria Bimo Waterproof Navy', 'hpp' => 45000, 'price' => 79000],
    ['sku' => 'SL-KULIT-BRW', 'name' => 'JAGOIN Waistbag Tas Selempang Pria Kulit Asli Cokelat', 'hpp' => 54000, 'price' => 76000],
    ['sku' => 'DOMPET-LOGO-BLK', 'name' => 'JAGOIN Dompet Pria Hanging Wallet Gantung Leher Hitam', 'hpp' => 28000, 'price' => 35000],
    ['sku' => 'DOMPET-SEC-GRY', 'name' => 'JAGOIN Dompet Pria Lipat Waterproof Anti Air Abu', 'hpp' => 21250, 'price' => 29000],
    ['sku' => 'TAS-HP-DRIVER', 'name' => 'JAGOIN Tas HP Driver Ojol Waterproof Holder Stang', 'hpp' => 18500, 'price' => 32000],
    ['sku' => 'RANSEL-CARRIER-60L', 'name' => 'JAGOIN Tas Ransel Carrier Gunung Outdoor 60L Cordura', 'hpp' => 115000, 'price' => 189000],
    ['sku' => 'WAISTBAG-CORDURA-ARMY', 'name' => 'JAGOIN Waistbag Tas Bahu Pria Cordura Tactical Army', 'hpp' => 32000, 'price' => 49000],
];

// Ambil product_variant_ids yang valid
$variants = $pdo->query("SELECT id, sku FROM product_variants LIMIT 20")->fetchAll(PDO::FETCH_ASSOC);

$stmtCheck = $pdo->prepare("SELECT COUNT(*) FROM marketplace_order_items WHERE order_id = ?");
$stmtInsert = $pdo->prepare("INSERT INTO marketplace_order_items 
    (order_id, product_variant_id, sku, item_name, qty, selling_price, unit_cogs_hpp, subtotal_price, subtotal_cogs)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");

$inserted = 0;
foreach ($orders as $ord) {
    $stmtCheck->execute([$ord['id']]);
    if ((int)$stmtCheck->fetchColumn() === 0) {
        // Jika adjustment, buat item adjustment
        if (str_starts_with($ord['id'], 'SHOPEE ADJ') || str_starts_with($ord['id'], 'TIKTOK ADJ')) {
            $stmtInsert->execute([
                $ord['id'],
                null,
                'ADJUSTMENT-FEE',
                'Penyesuaian Biaya Pengiriman & Komisi Marketplace',
                1,
                (float)$ord['gross_sales'],
                0,
                (float)$ord['gross_sales'],
                0
            ]);
            $inserted++;
            continue;
        }

        // Tentukan produk berdasarkan buyer_username atau rotasi
        $itemSeed = null;
        foreach ($sampleItems as $si) {
            if (stripos($ord['buyer_username'] ?? '', $si['sku']) !== false || stripos($ord['buyer_username'] ?? '', substr($si['sku'], 0, 6)) !== false) {
                $itemSeed = $si;
                break;
            }
        }
        if (!$itemSeed) {
            $itemSeed = $sampleItems[array_rand($sampleItems)];
        }

        $gross = (float)$ord['gross_sales'];
        $cogs = (float)$ord['total_cogs_hpp'];
        $qty = 1;
        if ($gross > 120000) {
            $qty = 2;
        }

        $varId = !empty($variants) ? $variants[array_rand($variants)]['id'] : null;

        $stmtInsert->execute([
            $ord['id'],
            $varId,
            $itemSeed['sku'],
            $itemSeed['name'],
            $qty,
            $gross > 0 ? round($gross / $qty, 2) : $itemSeed['price'],
            $cogs > 0 ? round($cogs / $qty, 2) : $itemSeed['hpp'],
            $gross,
            $cogs
        ]);
        $inserted++;
    }
}

echo "Berhasil memastikan seluruh pesanan ($inserted item baru) memiliki rincian produk di marketplace_order_items.\n";
