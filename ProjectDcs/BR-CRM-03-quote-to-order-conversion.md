# BR-CRM-03 — Quote → Order Conversion (C4)

**Modules:** ksf_FA_CRM ↔ FA Sales (order entry), substrate BR-COM-02
(quote state), BR-COM-03 (notify), BR-COM-01 (`get_dto`)
**Status:** PENDING (design ratified in this BR)
**Built on:** `0_fa_crm_quotes` already carries the full approval lifecycle
timestamps: status ('draft'), `approved_by/approved_date`, `sent_date`,
`accepted_date`, `rejected_date` + `valid_until`, `debtor_no`,
`opportunity_id`, `quote_items` table. `quotes.php`(228) has **1 approve / 1
reject match and no convert-to-order flow** (gaps doc C4). FA's own Sales
Orders table (`sales_orders`) stores `trans_no` with `debtor_no` + items on
`sales_order_details`.

## Business Need / Current State

The quote row is already a mini-workflow (draft→approved→sent→accepted),
but there's no **state machine** enforcing sequence (the page just flips
status), and **no path from an accepted quote into a real FA sales order**
— the customer-facing document means nothing to supply/logistics. C4 = close
the loop: accept a quote → create the sales order with the quote's items and
debtor, link `quote_id`, and let delivery/billing happen in FA where it
belongs (Dolibarr proposal→invoice; SuiteCRM Quote→Order analog).

## Business Requirement

The business requires:

1. **Quote lifecycle = BR-COM-02 process** — `quote.lifecycle` over the
   quote row: `draft→internal_approval→sent→accepted|rejected|expired`
   with guards: internal_approval requires approver + date; sent requires an
   approved/sent_date; accepted requires valid_until not passed;
   rejected requires note. Status flips become transitions (history rows).
2. **Accept → Sales Order conversion** — on `accepted`, a post-action builds
   an FA **sales order** (`sales_orders` + `sales_order_details`) from the
   quote's items/debtor/contact/terms; writes `opportunity_id` + the FA
   `trans_no` back onto the quote; marks the quote `converted`. Idempotent:
   a second accept finds `converted` and no-ops (no duplicate order).
3. **Conversion mapper** — `0_fa_crm_quote_items` → `sales_order_details`
   columns map (item, qty, price, tax) via a resolver; missing mappings
   error per row (item not found in FA inventory) without aborting the
   whole order (row-level error → quote gets a conversion gap note).
4. **Expiry guard** — accepting after `valid_until` refused (or explicit
   admin override recorded); BR-COM-04 job marks `expired` quotes and
   notifies owner (BR-COM-03) — scheduled expiry is C4's only clock.
5. **Won-pipeline linkage** — a converted quote transitions the BR-CRM-01
   opportunity to `won` (guard gets quote_id — closes the funnel loop with
   BR-CRM-01 AC2).
6. **Audit** — every transition + the conversion row-recorded (quote
   `converted_at`/`converted_trans_no`) — single FA audit trail for the
   order itself lives in FA.

## Scope

- In scope: BR-COM-02 quote process, accept→order conversion (resolver, item
  mapping, idempotence, row-gap report), expiry job, opportunity-won link,
  notify, unit/UAT.
- Out of scope: Quote→invoice (FA handles invoicing from the order),
  multi-currency conversion, complex bundle pricing (per-item map only).

## Design

### Process (BR-COM-02 registry) + conversion resolver

```php
// quote.lifecycle ProcessDefinition
states: draft -> internal_approval -> sent -> accepted|rejected|expired
start:  'draft' on quote create
user:   draft->internal_approval (Submit for review) [approver + no date yet]
user:   internal_approval->sent (Approve+Send)  [sets approved_by/date + sent_date]
user:   sent->accepted (Accept) [guard: valid_until >= today]
user:   sent->rejected (Reject, reason required)
auto:   sent->expired (BR-COM-04 job: valid_until passed + unconverted)

// post-action on accepted:
function crm_quote_convert(QuoteDto $q): array {
    if ($q->converted) return ['skipped' => 'already-converted'];   // idempotent
    $order = build_sales_order_from_quote($q);   // mapper per item row
    write_back_quote($q->id, $order->trans_no, 'converted');
    // opportunity.won transition (BR-CRM-01) via engine
    engine->transition($opp, 'won', 'system', "converted quote {$q->quote_no}");
    return ['trans_no' => $order->trans_no, 'gaps' => $unsupportedItemIds];
}
```

### Conversion mapper

```
quote_item (item_id?, description, qty, unit_price, tax) 
  -> stock_id      : item mapping (item_code in FA inventory or ERR)
  -> qty, unit_price, discount
  -> debtor_no, contact: pass-through; terms: from quote.terms
Unmapped item id -> row 'gap' recorded in quote.conversion_gaps (TEXT JSON);
remaining rows still create the order. Gap list shown on quote page.
```

## Supporting FRs

- FR-CRM-003-001 quote.lifecycle process + guards (approval, send, valid_until,
  reject-reason)
- FR-CRM-003-002 accept→sales-order conversion (resolver + write-back,
  idempotent)
- FR-CRM-003-003 item mapping with row-gap report (no full abort)
- FR-CRM-003-004 expiry job (BR-COM-04) + owner notify
- FR-CRM-003-005 opportunity→won on convert (BR-CRM-01 link)
- FR-CRM-003-006 unit/UAT

## Acceptance Criteria (UAT)

1. Unit: lifecycle transitions enforce sequence: reject/send before approval
   refused; accept after valid_until refused (admin-override recorded).
2. Unit: convert writes an FA sales order (sales_orders trans_no) with the
   quote's rows; quote marked converted + trans_no; second convert no-ops.
3. Unit: an unmapped item records a gap (JSON) and does not abort the rest;
   opportunity advances to won.
4. Unit: expiry job marks expired + notifies owner; accepted never re-sourced.
5. e2e (live FA container, `e2e_hrm_event_windows.php` mirror): seed quote +
   items + debtor → run to accepted → convert → assert sales_order rows +
   quote converted + opportunity won + history rows intact.

## Non-Functional

- PHP 7.3; PSR-4 `CRM\Quote\` (+ `CRM\SalesOrder\` mapper); FA `db_*` only
  (sales order written via FA-native stock/cart helpers or its tables
  directly + audit_trail — never a parallel order store); `0_` SQL literal.
- Conversion inside one FA transaction; item rows idempotent per (quote, stock).

## Related

- FA core Sales (the order is created there), BR-COM-02 (quote process),
  BR-CRM-01 (opportunity won closure), BR-COM-04 (expiry), BR-COM-03 (notify),
  C8 (accept/convert as activity-chain events)
- C4 gap; closes the C1 funnel loop (lead→opp→quote→order) — the first
  CRM BR that crosses into FA's financial core.