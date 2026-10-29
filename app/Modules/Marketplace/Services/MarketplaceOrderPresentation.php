<?php
declare(strict_types=1);

/** Presentation contract for verified connector facts; never infers COD from fees. */
final class MarketplaceOrderPresentation
{
    public const STATUS_LABELS=[
        'UNPAID'=>'Belum Dibayar','ON_HOLD'=>'Menunggu Konfirmasi',
        'AWAITING_SHIPMENT'=>'Perlu Dikirim','READY_TO_SHIP'=>'Perlu Dikirim','PROCESSED'=>'Perlu Dikirim',
        'AWAITING_COLLECTION'=>'Menunggu Pickup','PARTIALLY_SHIPPING'=>'Dikirim Sebagian',
        'IN_TRANSIT'=>'Sedang Dikirim','SHIPPED'=>'Sedang Dikirim','TO_CONFIRM_RECEIVE'=>'Sedang Dikirim',
        'DELIVERED'=>'Sudah Diterima','COMPLETED'=>'Selesai','CANCELLED'=>'Batal','Dibatalkan'=>'Batal',
        'IN_CANCEL'=>'Pengajuan Pembatalan','CANCEL_REQUESTED'=>'Pengajuan Pembatalan',
    ];
    public static function statusLabel(string $source,bool $cancelRequested=false):string
    {
        if($source!=='CANCELLED'&&$source!=='Dibatalkan'&&$cancelRequested)return 'Pengajuan Pembatalan';
        return self::STATUS_LABELS[$source]??$source;
    }
    public static function statusSql():string
    {
        $source="COALESCE(NULLIF(JSON_UNQUOTE(JSON_EXTRACT(transaction_detail_json,'$.source_status')),''),order_status)";
        $case="CASE WHEN $source NOT IN ('CANCELLED','Dibatalkan') AND JSON_EXTRACT(transaction_detail_json,'$.is_buyer_request_cancel')=true THEN 'Pengajuan Pembatalan' ELSE CASE $source";
        foreach(self::STATUS_LABELS as $key=>$label)$case.=" WHEN '$key' THEN '$label'";
        return $case." ELSE $source END END";
    }
    public static function build(array $order): array
    {
        $snapshot = json_decode((string)($order['transaction_detail_json'] ?? ''), true);
        $snapshot = is_array($snapshot) ? $snapshot : [];
        $gross = (float)($snapshot['gross_amount'] ?? $order['gross_sales'] ?? 0);
        $components = [];
        $labels = [
            'admin_fee' => ['Biaya Admin', 'platform_admin_fee', 1],
            'service_fee' => ['Biaya Layanan', 'service_fee', 1],
            'actual_shipping' => ['Ongkir Aktual', null, 1],
            'withholding_tax' => ['Pajak Pemotongan', null, 1],
            'seller_voucher' => ['Voucher dari Penjual', 'seller_discount', 1],
            'shipping_subsidy' => ['Subsidi Ongkir Shopee', null, -1],
            'processing_fee' => ['Biaya Pemrosesan Pesanan', 'payment_fee', 1],
            'shipping_adjustment' => ['Penyesuaian Ongkir', 'shipping_adjustment', 1],
            'other_deductions' => ['Potongan Lain', 'other_deductions', 1],
            'affiliate_commission' => ['Komisi Affiliate', 'affiliate_commission', 1],
            'live_commission' => ['Komisi Live', 'live_commission', 1],
            'shipping_discount' => ['Diskon Ongkir', null, -1],
            'buyer_shipping' => ['Ongkir Dibayar Pembeli', null, -1],
            'dynamic_commission' => ['Komisi Dinamis', null, 1],
            'platform_commission' => ['Komisi Platform', null, 1],
            'ams_admin_fee' => ['Biaya Admin AMS', null, 1],
            'cashback_fee' => ['Biaya Cashback', null, 1],
            'mall_fee' => ['Biaya Mall', null, 1],
            'logistics_fee' => ['Biaya Logistik', null, 1],
            'infrastructure_fee' => ['Biaya Infrastruktur', null, 1],
            'ads_affiliate_commission' => ['Komisi Ads Afiliasi', null, 1],
            'shipping_savings_fee' => ['Biaya Program Hemat Biaya Kirim', null, 1],
            'seller_transaction_fee' => ['Biaya Transaksi Penjual', null, 1],
            'shipping_protection' => ['Premi Proteksi Pengiriman', null, 1],
            'buyer_refund' => ['Pengembalian Dana ke Pembeli', null, 1],
            'paylater_fee' => ['Biaya PayLater', null, 1],
        ];
        foreach ($labels as $key => [$label, $legacy, $sign]) {
            $value = $snapshot['components'][$key] ?? ($legacy ? ($order[$legacy] ?? null) : null);
            if ($value === null) continue;
            $amount = (float)$value;
            $components[] = ['key' => $key, 'label' => $label, 'amount' => $amount, 'effect' => $sign, 'percent' => $gross > 0 ? round($amount / $gross * 100, 1) : null];
        }
        $componentTotal = array_sum(array_map(static fn(array $row): float => $row['amount'] * $row['effect'], $components));
        if(!empty($snapshot['components_signed'])){
            $components=[];
            foreach($snapshot['components']??[] as $key=>$amount)if(is_numeric($amount))$components[]=['key'=>$key,'label'=>(string)($snapshot['component_labels'][$key]??$labels[$key][0]??$key),'amount'=>abs((float)$amount),'effect'=>(float)$amount<0?-1:1,'percent'=>$gross>0?round(abs((float)$amount)/$gross*100,1):null];
            $componentTotal=array_sum(array_map(static fn($c)=>$c['amount']*$c['effect'],$components));
        }
        if(($order['source_kind']??'')==='api'&&array_key_exists('net_income',$snapshot)&&$snapshot['net_income']===null){$components=[];$componentTotal=0.0;}
        $fee = array_key_exists('total_fees',$snapshot)?($snapshot['total_fees']===null?null:(float)$snapshot['total_fees']):$componentTotal;
        $net = array_key_exists('net_income',$snapshot) ? ($snapshot['net_income']===null?null:(float)$snapshot['net_income']) : (isset($order['net_settlement_amount']) ? (float)$order['net_settlement_amount'] : null);
        $hpp = array_key_exists('total_cogs',$snapshot) ? ($snapshot['total_cogs']===null?null:(float)$snapshot['total_cogs']) : (isset($order['total_cogs_hpp']) ? (float)$order['total_cogs_hpp'] : null);
        $items = [];
        foreach (($snapshot['items'] ?? $order['items'] ?? []) as $item) {
            $qty = (float)($item['quantity'] ?? $item['qty'] ?? 0);
            $returned = isset($item['returned_quantity']) ? (float)$item['returned_quantity'] : null;
            $actual = $returned === null ? null : max(0, $qty - $returned);
            $items[] = [
                'name' => (string)($item['name'] ?? $item['item_name'] ?? $item['product_name'] ?? ''),
                'variant' => (string)($item['variant'] ?? $item['variant_name'] ?? ''),
                'sku' => (string)($item['sku'] ?? ''),
                'image_url' => (string)($item['image_url'] ?? ''),
                'quantity' => $qty, 'returned_quantity' => $returned, 'actual_quantity' => $actual,
                'unit_price' => isset($item['unit_price']) ? (float)$item['unit_price'] : null,
                'total_price' => isset($item['total_price']) ? (float)$item['total_price'] : (isset($item['subtotal_price']) ? (float)$item['subtotal_price'] : null),
                'unit_cogs' => isset($item['unit_cogs']) ? (float)$item['unit_cogs'] : (isset($item['unit_cogs_hpp']) ? (float)$item['unit_cogs_hpp'] : null),
                'total_cogs' => isset($item['total_cogs']) ? (float)$item['total_cogs'] : (isset($item['subtotal_cogs']) ? (float)$item['subtotal_cogs'] : null),
            ];
        }
        $sourceStatus = (string)($snapshot['source_status'] ?? $order['order_status'] ?? '');
        $status = self::statusLabel($sourceStatus,($snapshot['is_buyer_request_cancel']??false)===true);
        $profit = $net !== null && $hpp !== null ? $net - $hpp : null;
        return [
            'platform'=>(string)($order['platform']??''),
            'order_id' => (string)$order['id'], 'tracking_number' => (string)($snapshot['tracking_number'] ?? $order['tracking_number'] ?? ''),
            'payment_method' => (string)($snapshot['payment_method'] ?? $order['payment_method'] ?? ''),
            'is_cod' => $snapshot['is_cod'] ?? null,
            'is_estimated' => $snapshot['is_estimated'] ?? null,
            'finance_schema'=>$snapshot['finance_schema']??null,'finance_updated_at'=>$snapshot['finance_updated_at']??null,'finance_revenue'=>$snapshot['finance_revenue']??null,'subtotal_before_discount'=>$snapshot['finance_subtotal_before_discount']??null,'seller_discount'=>$snapshot['finance_seller_discount']??null,'unsettled_reason'=>$snapshot['unsettled_reason']??null,
            'source_status' => $sourceStatus, 'status' => $status,
            'order_date' => $order['order_date'] ?? null, 'released_at' => $snapshot['released_at'] ?? $order['completion_date'] ?? null,
            'source_updated_at' => $snapshot['source_updated_at'] ?? null,
            'items' => $items, 'components' => $components, 'gross_amount' => $gross,
            'total_fees' => $fee, 'net_income' => $net, 'total_cogs' => $hpp, 'net_profit' => $profit,
            'financial_state'=>$net===null?'waiting_income':($hpp===null?'waiting_hpp':'complete'),
            'fee_percent' => $gross > 0 && $fee!==null ? round($fee / $gross * 100, 1) : null,
            'profit_percent' => $gross > 0 && $profit !== null ? round($profit / $gross * 100, 1) : null,
            'adjustment_amount' => $snapshot['adjustment_amount'] ?? $order['adjustment_amount'] ?? null,
            'reconciliation_difference' => $net === null||$fee===null ? null : round(($gross - $fee + (float)($snapshot['adjustment_amount'] ?? $order['adjustment_amount'] ?? 0)) - $net, 2),
            'component_difference' => $fee===null?null:round($fee - $componentTotal, 2),
        ];
    }
}
