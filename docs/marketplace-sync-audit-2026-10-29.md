# Marketplace sync audit — 29 October 2026

## Changes verified in this pass

- Initial OAuth jobs now create dataset checkpoints and queue one product scan, orders, finance, and TikTok Shop returns. A separate variants job no longer repeats the product catalog.
- Subsequent order jobs use the completed range with a five-minute overlap and the provider's update-time filter. An order selected by that filter is kept even when it was created before the integration month. Initial capture still starts on the first day of the integration month.
- Finance jobs now consider previously created orders whose source order changed in the current completed range, even when their creation date is old. Finance that changes without any order update still needs a separate outstanding-object reconciliation path.
- Product and return listing endpoints have no verified update-time filter in the current adapters. Their successful reconciliations are scheduled at most once per day by default (`JL_MARKETPLACE_CATALOG_RECONCILE_SECONDS` and `JL_MARKETPLACE_RETURNS_RECONCILE_SECONDS`, minimum 900 seconds). A routine click before one is due makes no API call for that dataset and reports "Menunggu rekonsiliasi". Changes can therefore be delayed until that scan.
- Missing checkpoint schema now fails with `CHECKPOINT_SCHEMA_MISSING` instead of silently starting a legacy full-range job. The additive checkpoint migration was applied to the local database. The partner-settings table check no longer runs DDL inside a sync transaction.
- Sync status shows per-dataset new, updated, unchanged and failed counts, job ID, and a retry action for a failed job. The retry uses the existing scoped endpoint and resumes that job's saved checkpoint.

## Current connector matrix

| Dataset | Shopee seller | TikTok Shop seller | TikTok Ads |
|---|---|---|---|
| Products, variants, prices, stock | Implemented, subject to seller permission | Implemented, subject to seller permission | Not a seller catalog connector |
| Orders and status | Implemented, subject to seller permission | Implemented, subject to seller permission | Not an order connector |
| Shipping detail | Order detail fields only; document connector not implemented | Existing fulfillment/document path, subject to permission | Not available in this connector |
| Returns | Dedicated sync not implemented | Search endpoint implemented, subject to permission | Not an order connector |
| Finance | Escrow detail endpoint implemented, subject to permission; missing detail stays unavailable | Unsettled estimate and statement endpoints implemented, subject to permission | Not seller finance |
| Withdrawals | API connector not implemented | API connector not implemented | Not implemented |
| Ads | No approved Ads connector wired to sync | Separate TikTok Ads client exists, but advertiser OAuth and scheduled ingestion are not wired to sync | Not operational |

Implemented does not mean the connected seller has permission. Capability evidence is recorded after an actual API response; authorization, permission, and temporary failures have separate states.

## Endpoint paths used by the current code

- Shopee: `/api/v2/product/get_item_list`, `/api/v2/product/get_item_base_info`, `/api/v2/product/get_model_list`, `/api/v2/order/get_order_list`, `/api/v2/order/get_order_detail`, `/api/v2/payment/get_escrow_detail`.
- TikTok Shop: `/product/202309/products/search`, `/product/202309/products/{id}`, `/order/202309/orders/search`, `/order/202309/orders`, `/return_refund/202602/returns/search`, `/finance/202507/orders/unsettled`, `/finance/202501/orders/{id}/statement_transactions`.
- The TikTok Ads class targets TikTok Marketing API v1.3. It is not part of seller sync and has no verified advertiser authorization in this installation.

Endpoint names and versions above are code inspection, not proof of live portal approval or quota. No live platform read or write was performed in this pass.

## Tests and limits

The isolated database sync, OAuth, Shopee catalog, and integration-runtime smoke tests pass. The sync test verifies a completed checkpoint, advancement after an empty successful order range, no repeat initial order filter, acceptance of an older updated order, and finance selection of that order. The focused incremental rule test confirms that a pre-integration order selected by update time is accepted and a catalog job before its reconciliation interval does no API read. The database tests use temporary schemas and fixture API responses; no live token or quota was exercised.

Webhook ingestion remains disabled with HTTP 503 because platform signature verification and a durable inbox have not been verified. The server CLI scheduler and Windows background worker exist, but this pass did not verify a continuously running production scheduler. Finance and return reconciliation for old objects without a new order update, withdrawal API ingestion, TikTok Ads advertiser authorization and ingestion, and administrative full-sync preview remain open. Do not present these datasets as realtime or complete.
