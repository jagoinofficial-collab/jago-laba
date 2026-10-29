<?php
declare(strict_types=1);
require_once (dirname(__DIR__, 2) . '/app/Modules/Marketplace/Services/MarketplaceFinanceNormalizer.php');
require_once (dirname(__DIR__, 2) . '/app/Modules/Marketplace/Services/MarketplaceOrderPresentation.php');
function financeCheck(bool $ok,string $message):void{if(!$ok)throw new RuntimeException($message);}
$sku=['revenue_breakdown'=>['subtotal_before_discount_amount'=>'100000','seller_discount_amount'=>'-10000'],
 'fee_tax_breakdown'=>['fee'=>['platform_commission_amount'=>'-10000','affiliate_commission_amount'=>'-4000','affiliate_commission_amount_before_pit'=>'-4500'],'tax'=>['article_22_income_tax_withheld'=>'-1000']],
 'shipping_cost_breakdown'=>['actual_shipping_fee_amount'=>'-8000','shipping_fee_discount_amount'=>'3000','supplementary_component'=>['shipping_fee_discount_amount'=>'500000']]];
$source=['order_id'=>'O','settlement_amount'=>'140000','revenue_amount'=>'180000','fee_and_tax_amount'=>'-30000','shipping_cost_amount'=>'-10000','sku_transactions'=>[$sku,$sku]];
$snapshot=MarketplaceFinanceNormalizer::apply(['gross_amount'=>180000,'total_cogs'=>80000],$source);
$view=MarketplaceOrderPresentation::build(['id'=>'O','transaction_detail_json'=>json_encode($snapshot)]);
financeCheck($view['gross_amount']===200000.0&&$view['net_income']===140000.0&&$view['net_profit']===60000.0,'source gross/net/profit incorrect');
financeCheck($view['total_fees']===60000.0&&$view['component_difference']===0.0,'fees/subsidies double counted');
$c=array_column($view['components'],'amount','key');$effects=array_column($view['components'],'effect','key');financeCheck($c['affiliate_commission']===8000.0&&$c['shipping_discount']===6000.0&&$effects['shipping_discount']===-1&&$c['withholding_tax']===2000.0,'fee categories or signs incorrect');
financeCheck(MarketplaceFinanceNormalizer::apply($snapshot,$source)===$snapshot,'normalizer not idempotent');
$empty=['settlement_amount'=>'0','revenue_amount'=>'0','fee_and_tax_amount'=>'0','shipping_cost_amount'=>'0','sku_transactions'=>[]];
$pending=MarketplaceFinanceNormalizer::apply(['gross_amount'=>175000,'total_cogs'=>45000],$empty);
$view=MarketplaceOrderPresentation::build(['id'=>'P','transaction_detail_json'=>json_encode($pending)]);
financeCheck($view['net_income']===null&&$view['net_profit']===null&&$view['total_fees']===null,'empty API response treated as zero income / 100 percent fee');
financeCheck(MarketplaceFinanceNormalizer::apply($snapshot,$empty)===$snapshot,'empty response erased confirmed finance');
$oldFalseZero=['gross_amount'=>175000,'net_income'=>0,'total_fees'=>175000,'finance_available'=>true,'finance_schema'=>'tiktok_202501'];financeCheck(MarketplaceFinanceNormalizer::apply($oldFalseZero,$empty)['net_income']===null,'legacy empty-source zero retained as confirmed finance');
$zero=MarketplaceFinanceNormalizer::apply(['gross_amount'=>100,'total_cogs'=>0],['settlement_amount'=>'0','revenue_amount'=>'100','fee_and_tax_amount'=>'-100','shipping_cost_amount'=>'0','sku_transactions'=>[]]);
$view=MarketplaceOrderPresentation::build(['id'=>'Z','transaction_detail_json'=>json_encode($zero)]);financeCheck($view['net_income']===0.0&&$view['net_profit']===0.0,'confirmed zero confused with unavailable');
$credit=MarketplaceFinanceNormalizer::apply(['gross_amount'=>100,'total_cogs'=>0],['settlement_amount'=>'110','revenue_amount'=>'100','fee_and_tax_amount'=>'10','shipping_cost_amount'=>'0','sku_transactions'=>[]]);
financeCheck($credit['components']['marketplace_fee_tax']===-10.0,'fee reimbursement treated as expense');
echo "PASS: signed fees, tax, subsidy, seller discount, multi-SKU totals, supplementary exclusions, unknown income, true zero and idempotence.\n";

$estimated=['order_id'=>'REAL-SHAPED','est_settlement_amount'=>'104002','est_revenue_amount'=>'128340','est_fee_tax_amount'=>'-24338','est_shipping_cost_amount'=>'0','revenue_breakdown'=>['subtotal_before_discount_amount'=>'225000','seller_discount_amount'=>'-96660'],'fee_tax_breakdown'=>['fee'=>['dynamic_commission_amount'=>'-10267','mall_service_fee_amount'=>'-2310','vn_fix_infrastructure_fee'=>'-1250','affiliate_commission_before_pit_amount'=>'-5000']]];
$estimate=MarketplaceFinanceNormalizer::applyUnsettled(['gross_amount'=>110400,'total_cogs'=>59000],$estimated);$v=MarketplaceOrderPresentation::build(['id'=>'REAL-SHAPED','source_kind'=>'api','transaction_detail_json'=>json_encode($estimate)]);
financeCheck($v['gross_amount']===128340.0&&$v['total_fees']===24338.0&&$v['net_income']===104002.0&&$v['net_profit']===45002.0&&$v['is_estimated']===true,'unsettled net revenue/HPP profit incorrect');financeCheck($v['component_difference']===0.0&&$v['reconciliation_difference']===0.0,'unsettled remainder reconciliation incorrect');
$confirmed=$snapshot;$confirmed['is_estimated']=false;financeCheck(MarketplaceFinanceNormalizer::applyUnsettled($confirmed,$estimated)===$confirmed,'estimate overwrote final statement');
$unknown=MarketplaceOrderPresentation::build(['id'=>'MISSING','source_kind'=>'api','platform_admin_fee'=>0,'transaction_detail_json'=>json_encode(['net_income'=>null,'total_fees'=>null])]);financeCheck($unknown['components']===[],'missing finance displayed zero fees');echo "PASS: unsettled API totals, missing breakdown remainder, estimated profit after HPP and final precedence.\n";
