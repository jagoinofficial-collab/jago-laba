# Owner decision: orphan inventory variant references (2026-11-01)

## Scope and safety

Prepared from the disposable Step 11 Marketplace restore, schema
`jl_step11_market_retry_20261101`, using SELECT-only inspection. The temporary
MariaDB instance listened on `127.0.0.1:3317` and is stopped. No source dump,
production database, or row was modified. This is a decision packet, not an
approved repair.

## Finding

`inventory_stocks.product_variant_id` references 17 values absent from
`product_variants.id`. The stock IDs are legacy-looking `STK-*` values while
the current variant identifiers in the dump use `VAR-*`; this naming pattern
is evidence only and does not prove a mapping.

- Affected rows: 17
- Total on hand: 1,720 units
- Implied stock valuation (`stock_qty * average_hpp`): 62,494,000
- `stock_mutations` rows referencing these 17 missing IDs: 0
- No mapping or correction was inferred or applied.

## Affected stock rows

| inventory_stocks.id | Missing product_variant_id | stock_qty | average_hpp | Implied value |
|---:|---|---:|---:|---:|
| 1 | `STK-BAE4F330` | 0 | 45,000.00 | 0.00 |
| 2 | `STK-4405BDF1` | 0 | 45,000.00 | 0.00 |
| 3 | `STK-6E8D0D7A` | 20 | 45,000.00 | 900,000.00 |
| 4 | `STK-4EBDD0CE` | 418 | 45,000.00 | 18,810,000.00 |
| 5 | `STK-D1E7EE15` | 728 | 58,000.00 | 42,224,000.00 |
| 6 | `STK-F5768279` | 0 | 90,000.00 | 0.00 |
| 7 | `STK-3441A080` | 20 | 28,000.00 | 560,000.00 |
| 22 | `STK-E22F0A32` | 10 | 0.00 | 0.00 |
| 23 | `STK-CDC85C19` | 10 | 0.00 | 0.00 |
| 24 | `STK-AB959F6A` | 0 | 0.00 | 0.00 |
| 25 | `STK-47B6E3F7` | 0 | 0.00 | 0.00 |
| 26 | `STK-7A78B9B6` | 0 | 0.00 | 0.00 |
| 27 | `STK-AD4A9502` | 0 | 0.00 | 0.00 |
| 28 | `STK-78BB3F9B` | 260 | 0.00 | 0.00 |
| 29 | `STK-FA5A3944` | 20 | 0.00 | 0.00 |
| 30 | `STK-1EBEE487` | 170 | 0.00 | 0.00 |
| 31 | `STK-B81E0804` | 64 | 0.00 | 0.00 |

## Owner decisions required

For each missing ID above, the Marketplace/inventory data owner must provide
one of these explicit outcomes, backed by the legacy product/variant master or
another authoritative record:

1. The exact canonical `product_variants.id` it represents; or
2. Confirmation that it is a retired/deleted variant, plus the approved
   disposition of its stock quantity and HPP balance; or
3. Confirmation that the legacy `STK-*` value is intentionally a different
   identifier class and an approved schema/import mapping rule.

The owner must also confirm whether zero-HPP balances are expected and how the
1,720-unit total and implied valuation reconcile to the inventory ledger. A
matching quantity, HPP, product name, or ID prefix by itself is not sufficient
evidence to map or discard a row.

## Decision record (owner to complete)

- Owner/name and role:
- Source used to establish mappings:
- Per-ID mapping/disposition attached:
- Quantity and valuation reconciled to ledger: yes/no; reference:
- Zero-HPP rows disposition:
- Approved by/date:
- Follow-up change request/backup reference (if any):

Until this record is completed and independently checked, the restore remains
**NO-GO** for migration/cutover. Any data repair must be a separate, reviewed,
backed-up task with explicit mapping and before/after validation; this packet
does not authorize that repair.
