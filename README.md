# Exemptax Magento Integration (Phase 2)

Local Magento 2 / Mage-OS module that:

- Adds customer attributes for exemption metadata (`exemptax_exemption_states` multiselect, `exemptax_exemption_status`, `exemptax_main_exemption_type`)
- POSTs customer create/update/delete events to Exemptax `/wbhk/adbcmmrc/event`

## Client setup (no Marketplace)

Clients install this module themselves, then **Activate** the EXEMPTAX integration in Magento Admin. They do **not** paste Callback URL, Identity Link URL, Webhook URL, Settings URL, or ex-key by hand — OAuth URLs are pre-filled on install, and the rest are pushed after Activate.

### 1. Install the module

Copy `Exemptax/Integration` into `app/code/Exemptax/Integration` (or require the composer package), then:

```bash
bin/magento module:enable Exemptax_Integration
bin/magento setup:upgrade
bin/magento cache:flush
```

`setup:upgrade` creates a Magento Integration named **EXEMPTAX** with production OAuth URLs pre-filled:

- **Callback URL:** `https://app.exemptax.com/adobe_commerce/oauth/callback`
- **Identity Link URL:** `https://app.exemptax.com/adobe_commerce/app`

### 2. Activate

**System → Extensions → Integrations → EXEMPTAX → Activate**

Callback and Identity Link URLs are already filled with production defaults. Magento opens the Identity Link popup → merchant logs into EXEMPTAX → picks company → tax engine / exemption automation → **Connect & Sync**.

If this Magento store connects to **DEV** or **local Herd** instead of production, replace the two URLs in that integration before Activate:

- **DEV:** `https://a-dvlp-01.exemptax.com/api/v1/adobe_commerce/oauth/callback` and `.../app`
- **Local Herd:** `https://app-api.exemptax.test/adobe_commerce/oauth/callback` and `https://app.exemptax.test/adobe_commerce/app`

Leave **API** resources as pre-selected (Customers, Customer Groups, Directory, EXEMPTAX Webhook Settings).

After that, EXEMPTAX pushes Webhook URL, Settings URL, ex-key, and related config into **Stores → Configuration → EXEMPTAX**. Merchants can later change sync settings there without opening EXEMPTAX.

## Local OAuth Activate (Callback URL)

Magento Integration **Callback URL** must be reachable from the Magento container (see table above).

Identity Link flow: Authorize → Login → Company → **Settings** (tax engine, exemption automation, customer groups) → Connect & Sync. Chosen settings are stored before the first Sync so Magento module config matches (`apply_state_exemptions`, `ac_customer_groups`). Ecommerce certificate form stays enabled by default on connect.

If Activate fails with *"The attempt to post data to consumer failed..."*, Magento usually cannot trust Herd’s TLS cert. This module replaces Magento’s `OauthService` and disables SSL verify for `*.test` / `*.local` callback hosts. After changing `di.xml`, run `bin/magento setup:di:compile` (compiled DI) and `bin/magento cache:flush`.

Also mount `certs/herd-ca.crt` (Herd Valet CA) into the PHP container via `compose.dev.yaml` for other HTTPS calls.

Exemptax BE also:
- verifies Magento OAuth tokens before hydrating Identity Link / starting sync
- marks `needs_reauth` on HTTP 401 so settings are not locked forever as “sync in progress”

## Configure

After Exemptax connect / Sync, Magento settings are **auto-pushed** via `PUT /V1/exemptax/integration/webhook-settings` (webhook URL, ex-key, enabled, verify_ssl, apply_state_exemptions, ecommerce drop URL).

On **Disconnect**, Exemptax pushes Magento cleanup **before** deleting OAuth tokens:
- webhooks `enabled=false`
- `apply_state_exemptions=false`
- `ecommerce_drop_enabled=false` (hides My Account iframe / footer link)

Customer exemption attributes are left as-is (no mass clear). If Magento is unreachable, disconnect still completes.

Manual fallback — Admin → Stores → Configuration → EXEMPTAX → Integration:

- Enable webhooks: Yes
- Webhook URL: local Herd `https://app-api.exemptax.test/wbhk/adbcmmrc/event` (or the URL shown in Exemptax Adobe settings)
- Exemptax ex-key: `webhook_ex_key` from Exemptax Adobe Commerce settings
- Verify SSL: No for local `*.test` / Herd; Yes in production

The Magento Integration OAuth token needs ACL `Exemptax_Integration::webhook_settings` (or Resource Access: All).

## Webhook timing

Webhooks are sent **after Magento commits** customer DB changes:

- `customer_save_after_data_object` → immediate post (CustomerRepository / admin form / inline edit)
- `customer_save_commit_after` → deferred fallback for direct model saves (skipped if data_object already posted)
- `customer_delete_commit_after` → after delete commit
- `customer_address_save_commit_after` → `customer/address/save` (address-only Admin/API paths)
- `customer_address_delete_before` → queues `customer/address/delete` after commit (resource delete clears address data before `delete_commit_after`)

Same-request customer + address saves are deduped (one POST per customer id). Payload always sends Magento **customer** id in `data.id` (Exemptax re-GETs the full customer).

Do **not** use `customer_save_after` — it runs before commit and can make Exemptax GET stale customer data.

### No webhook echo on Exemptax pushes

When Exemptax BE writes Magento customers (exemption attrs, etc.), it sends header:

`X-Exemptax-Origin: push`

The Magento module **skips** outbound webhooks for that request so Magento does not echo the same save back to Exemptax. Merchant / storefront / admin edits (no header) still webhook normally.

## Production readiness (later)

1. API Gateway → SQS → Lambda for `adbcmmrc`
2. ~~Webhook hardening (require valid `ex-key` before `IntegrationEvent`; HMAC)~~ — done (see Auth below)
3. ~~Auto-push Magento `webhook_url` + `ex_key` on connect~~ — done via Sync `checkSetup` → `PUT /V1/exemptax/integration/webhook-settings`
4. **Customer groups + exclusion (last)**

### Webhook auth

Magento POSTs must include:

| Header | Value |
|---|---|
| `ex-key` | Exact `webhook_ex_key` from Exemptax Adobe Commerce settings (`encrypt(company_id)`) |
| `X-Exemptax-Hmac-Sha256` | `base64(hmac-sha256(raw JSON body, ex-key))` |

Exemptax rejects missing/invalid key or HMAC with **401** and does **not** create an `IntegrationEvent`. The same checks run again when the event is processed.

## Native tax plugin

When **Apply state-based tax exemptions** is Yes (default), after quote address totals are collected Magento tax is zeroed if:

1. Customer is logged in / quote has a customer id
2. Ship-to country is US
3. Ship-to region code is in customer attribute `exemptax_exemption_states` (comma-separated state codes, e.g. `IL,MI` — Admin UI is a US-state multiselect)

### Account Information — EXEMPTAX Exempt Regions
- Attribute: `exemptax_exemption_states` (label **EXEMPTAX Exempt Regions**)
- Admin form: multiselect of US states (codes stored as `IL,MI`)
- Data patch: `ConvertExemptionStatesToMultiselect` (`bin/magento setup:upgrade`)
- EXEMPTAX BE continues to push CSV state codes; native tax hook and TaxJar `tj_regions` mapping are unchanged

Hook: `sales_quote_address_collect_totals_after` (native Magento tax engine only).

When Exemptax `tax_engine=taxjar`, the backend also writes TaxJar Magento customer attrs (`tj_exemption_type`, `tj_regions`).

## Magento Admin — EXEMPTAX → Settings

**Stores → Configuration → EXEMPTAX → Settings** is a **live two-way mirror** of EXEMPTAX Adobe Commerce settings so merchants can change sync settings in Magento without opening the EXEMPTAX app.

- Tax engine (`magento` | `taxjar`)
- Exemption status update automation (`0`–`3`)
- Customer groups / sync tags (when shown)

**Load / Refresh** = HMAC `GET` Laravel `/wbhk/adbcmmrc/settings` (not API Gateway `/event`).
**Save Changes** = HMAC `POST` the same URL. EXEMPTAX writes Integration settings, locks, and starts Sync. Sync then **pushes** Magento REST `PUT /V1/exemptax/integration/webhook-settings` (`settingsUrl`, tax engine, automation, groups, lock). While locked, Magento shows **Sync in progress**, disables fields, and **Refresh** polls EXEMPTAX (every 5s). Unlock requires the review checkbox before Save, matching taxeros.

`settings_url` is the Laravel app API (Herd `app-api…/wbhk/adbcmmrc/settings`, or DEV `https://a-dvlp-01.exemptax.com/api/v1/wbhk/adbcmmrc/settings`). Do **not** point it at `execute-api…/event`.

**Customer groups** (multiselect): Magento lists local groups; selected IDs are stored as `ac_customer_groups` in EXEMPTAX. Empty/`[]` means allow all. Deselected groups still sync but as **inactive**. All groups are selected by default in the UI.

Requires Integration **Settings URL** + ex-key (Settings URL is auto-pushed on Sync; locally it can be derived from a non-Gateway webhook URL).

TaxJar SmartCalcs reads exemptions from **TaxJar’s cloud customer**, not Magento EAV alone. TaxJar’s own Magento observer only syncs those attrs on **Admin** customer save. This module adds a `CustomerRepository::afterSave` plugin that syncs `tj_*` to TaxJar’s customer API for REST / non-admin saves (Exemptax pushes), so checkout automation works.

Keep this Magento native tax hook disabled or unused on TaxJar stores (one checkout tax path).

Exemptax auto-pushes on Sync / settings save via
`PUT /V1/exemptax/integration/webhook-settings`:
- `tax_engine=magento` → `apply_state_exemptions=1`
- `tax_engine=taxjar` (or any non-magento engine) → `apply_state_exemptions=0`
- company settings mirror: `settingsUrl`, `taxEngine`, `taxExemptFlag`, `acCustomerGroups`, `syncCustomerTags`, `lastSyncAt`, `settingsLocked`

## Ecommerce certificate form (Shopify-style embed)

Logged-in Magento customers can open the EXEMPTAX `/ecommerce-drop` form from **My Account** (iframe) or the **footer** (popup), same pattern as Shopify.

### Exemptax BE
`GET /ecommerce-drop` (Angular) / session create accepts:

| Param | Required | Notes |
|---|---|---|
| `integration_type` | yes | `adobe_commerce` (alias: `magento`) |
| `store_base_url` | preferred | Must match Adobe Commerce `realm_id` / store base URL |
| `customer_id` | yes | Magento customer entity id |
| `email` | recommended | Must match synced billing email unless ignore-email setting |

Connect already sets `ecommerce_enabled=true` on the Adobe Commerce integration.

### Magento Admin config
**Stores → Configuration → EXEMPTAX → Integration**

On Exemptax connect / Sync, these are **auto-pushed** via `PUT /V1/exemptax/integration/webhook-settings`:
- Webhook URL, ex-key, enabled, verify_ssl, apply_state_exemptions
- **Ecommerce drop URL** from that environment’s `FE_URL_BASE` + `/ecommerce-drop` (and ecommerce link enabled)

Then `bin/magento cache:flush` if you change values by hand.

### What the module renders
- **Storefront page** `/tax-exempt-certificates`: CMS page with merchant-editable copy + iframe of ecommerce-drop
- **My Account** dashboard: iframe of ecommerce-drop
- **Footer** (logged-in only): “Tax-Exempt Certificates (New Tab)” opens `/tax-exempt-certificates` in a new tab; “(Popup)” opens ecommerce-drop directly

### Storefront page (`/tax-exempt-certificates`)

Mirrors the Shopify `tax-exempt-customers` page: a store-owned URL whose copy is CMS content, wrapping an iframe of the same ecommerce-drop app.

- Created on `setup:upgrade` by `Setup\Patch\Data\CreateCertificatesCmsPage`. The patch **skips** if a page with that URL key already exists, so merchant edits are never overwritten.
- Copy is editable in **Content → Pages → Tax Exempt Certificates**. Deleting the page does not break anything except the footer link target.
- The iframe is injected by `view/frontend/layout/cms_page_view_id_tax-exempt-certificates.xml`, marked `cacheable="false"` because the iframe `src` embeds `customer_id` and `email`. The handle is derived from the page URL key (Magento only converts `/` to `_`, so hyphens are kept).
- Logged-out visitors see the CMS copy plus a “Sign In” prompt instead of the iframe.
- The page is `NOINDEX,NOFOLLOW`; it is only useful to logged-in customers.

The storefront URL key defaults to `tax-exempt-certificates` (`exemptax_integration/general/certificates_page_identifier`). It is not shown in Admin. Renaming the CMS page also requires updating that config path and renaming `cms_page_view_id_tax-exempt-certificates.xml`.

### Manual theme snippet (optional / Shopify-style)
Popup footer link:

```html
<a href="#" onclick="window.open(
  'https://app.exemptax.test:4200/ecommerce-drop'
    + '?integration_type=adobe_commerce'
    + '&store_base_url=' + encodeURIComponent('https://magento.test:9443')
    + '&customer_id=CUSTOMER_ID'
    + '&email=' + encodeURIComponent('customer@example.com'),
  'exemptionRequest',
  'left=50,top=100,width=1000,height=600,menubar=0,toolbar=0,location=0,status=0'
); return false;">Tax-Exempt Certificates</a>
```

### Test checklist
1. Customer synced to EXEMPTAX (billing_erp id = Magento customer id)
2. Integration `ecommerce_enabled` true
3. Log in on storefront → My Account shows iframe, or footer opens popup
4. Complete a cert → appears in EXEMPTAX for that billing

## Phase 2.1 — Main exemption type → TaxJar

TaxJar allows **one** `exemption_type` per customer. Phase 2.1 makes that explicit and maps EXEMPTAX reasons instead of always writing `other`.

### Magento customer attribute
- **EXEMPTAX Main Exemption Type** (`exemptax_main_exemption_type`) — Admin Account Information
- Sublabel: *Primary exemption reason determined by EXEMPTAX. For TaxJar, this is mapped to the customer's exemption type.*
- Data patch: `AddMainExemptionTypeAttribute` (`bin/magento setup:upgrade`)

### Mapping (EXEMPTAX → TaxJar `tj_exemption_type`)
| EXEMPTAX | TaxJar |
|---|---|
| Resale (`G`) | Wholesale |
| Government — federal (`A`), state (`B`), tribal (`C`) | Government |
| Everything else | Other |
| Taxable / no coverage | Non-Exempt |

### Implementation
- Source “main” reason from billing `default_exemption_reason_id` (label from reason name).
- BE: `AdobeCommerceService::mapExemptaxReasonToTaxJarType()` + `updateCustomerExemptionAttributes()` writes Magento attr + `tj_exemption_type` / `tj_regions` when `tax_engine=taxjar`.
- Existing TaxJar cloud sync plugin still applies on Magento customer save.
- Regions still control *where* checkout is $0; type is classification for TaxJar calc/reporting.
- Note: the reason→type rules match Chargebee’s shared `convertExemptionReasonTaxjar()`. Adobe keeps its own mapper because it must also emit `non_exempt` and tolerate a null/lowercase reason id.
