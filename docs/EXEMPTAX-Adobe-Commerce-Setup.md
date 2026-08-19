# EXEMPTAX Adobe Commerce Setup 1.0 – Updated August 18, 2026

# EXEMPTAX — Adobe Commerce / Magento Setup

This guide is for merchants connecting Adobe Commerce (Magento) or Magento Open Source to EXEMPTAX. It covers install, Activate, and the storefront certificate experience.

Unlike Shopify, you do **not** paste a certificate snippet into the theme for the default experience. After Activate, EXEMPTAX is already available on My Account, a storefront CMS page, and the footer.

## 1. Integration method

Your Adobe Commerce or Magento Open Source store connects to EXEMPTAX using the **EXEMPTAX Magento module** (`exemptax/module-integration`), installed with Composer.

Supported editions:

- Magento Open Source
- Adobe Commerce (on-prem / self-hosted)
- Adobe Commerce Cloud

After the module is installed, Magento Admin lists an integration named **EXEMPTAX**. You Activate that integration; you do not paste Callback URL, Identity Link URL, Webhook URL, or an ex-key by hand.

## 2. Start from EXEMPTAX

Once your EXEMPTAX account is set up, log in at [https://app.exemptax.com/](https://app.exemptax.com/) and open the Adobe Commerce setup wizard for your company.

The wizard shows:

1. The Composer commands for your edition
2. How to Activate **EXEMPTAX** in Magento Admin
3. A connection check after Activate

Keep the EXEMPTAX tab open while you finish Activate in Magento.

## 3. Installation

Run all Magento CLI commands as the **site user** (not root), from the Magento / Adobe Commerce project root (the directory that contains `bin/magento` and `composer.json`).

`composer require` without a version installs the latest **stable** release from Packagist. Composer then writes a caret constraint (for example `^1.0.5`) into `composer.json`.

### A. Magento Open Source or Adobe Commerce (self-hosted)

1. Connect to the server over SSH.
2. Change to the Magento root.
3. Run:

```bash
composer require exemptax/module-integration
php bin/magento module:enable Exemptax_Integration
php bin/magento setup:upgrade
php bin/magento cache:flush
```

4. Confirm Magento Admin → **System → Extensions → Integrations** lists **EXEMPTAX**.

`setup:upgrade` creates the EXEMPTAX integration with production OAuth URLs already filled:

- **Callback URL:** `https://app.exemptax.com/api/v1/adobe_commerce/oauth/callback`
- **Identity Link URL:** `https://app.exemptax.com/api/v1/adobe_commerce/app`

It also creates the storefront CMS page **Tax Exempt Certificates** at `/tax-exempt-certificates`.

### B. Adobe Commerce Cloud

Do this on a **development branch** (local clone or Cloud IDE). Do not run Composer on production SSH.

```bash
composer require exemptax/module-integration --no-update
composer update
git add composer.json composer.lock app/etc/config.php
git commit -m "Install EXEMPTAX Magento module"
git push origin YOUR_BRANCH
```

Replace `YOUR_BRANCH` with your Cloud branch name. Wait for Cloud build and deploy. Test on staging, then merge to production.

If EXEMPTAX is missing after deploy, run these **locally** (not on production SSH), then commit `app/etc/config.php` and push again:

```bash
php bin/magento module:enable Exemptax_Integration
./vendor/bin/ece-tools module:refresh
```

## 4. Activate

In Magento Admin:

1. Go to **System → Extensions → Integrations**.
2. Find **EXEMPTAX**.
3. Click **Activate**.
4. Allow the requested API access (Customers, Customer Groups, Directory, EXEMPTAX Webhook Settings).
5. Magento opens a window to EXEMPTAX. Sign in, pick this company, choose tax engine / exemption automation, then **Connect & Sync**.

Leave API resources as pre-selected. Callback and Identity Link URLs are already filled; do not change them for production.

After Connect & Sync, EXEMPTAX pushes webhook URL, settings URL, ex-key, and certificate-form URL into Magento. You can later change sync settings under **Stores → Configuration → EXEMPTAX** without opening the EXEMPTAX app.

When EXEMPTAX shows **Connected**, setup is complete.

## 5. EXEMPTAX plugin (storefront)

The module turns on the certificate experience after Connect & Sync. You do not need theme-code changes for the default placement.

This documentation focuses on:

- **A.** My Account (iframe)
- **B.** Storefront CMS page `/tax-exempt-certificates` (iframe)
- **C.** Footer links (new tab + popup)
- **D.** Optional customizations (CMS copy, navigation, custom theme)

EXEMPTAX does not provide UI or UX guidance beyond these defaults. Placement and wording can be adjusted to match your store.

The certificate form only works for a **logged-in Magento customer**, because exemptions are tied to that customer account.

### A. My Account

Logged-in customers see the EXEMPTAX certificate iframe on the My Account dashboard (**Customers → Account** / storefront **My Account**).

No theme edit is required. The iframe is injected by the module.

If **Enable ecommerce certificate link** is No under **Stores → Configuration → EXEMPTAX → Integration**, My Account and the footer links are hidden.

### B. Storefront page (`/tax-exempt-certificates`)

Install creates a CMS page:

- **Title:** Tax Exempt Certificates
- **URL key:** `tax-exempt-certificates`
- **Storefront URL:** `https://YOUR-STORE/tax-exempt-certificates`

Page copy (editable):

> If you're a tax exempt customer, you may submit tax exemption documents using the steps below:
>
> 1. Click on the **Submit Certificates** button to dynamically generate a tax exemption certificate through a guided flow. If you already have a completed tax document, click on the **Upload Pre-Completed Certificates** button.
> 2. Once you submit your document(s), you will be able to proceed with checkout exempt from sales tax.
> 3. Please note that if your document is expired, or is determined to be invalid at a later date, we may collect sales tax, and reach out to you offline to address any issues.
>
> If you have any questions specific to tax, please contact your tax advisor.

Logged-in customers then see the EXEMPTAX iframe (Submit Certificates / Upload Pre-Completed Certificates). Logged-out visitors see the CMS copy and a **Sign In** prompt instead of the iframe.

To edit copy: **Content → Pages → Tax Exempt Certificates**. Saving the page does not remove the iframe. Deleting the page breaks the footer “New Tab” target until the page is recreated.

The page is `NOINDEX,NOFOLLOW`. It is meant for logged-in customers, not organic search.

### C. Footer

For logged-in customers, the footer includes:

| Link | What it does |
|---|---|
| **Tax-Exempt Certificates (New Tab)** | Opens `/tax-exempt-certificates` in a new browser tab |
| **Tax-Exempt Certificates (Popup)** | Opens the EXEMPTAX certificate form in a popup window |

Logged-out visitors do not see these links.

### D. Optional customizations

**Add the page to a menu (header or footer CMS block)**

1. **Content → Elements → Pages** and confirm **Tax Exempt Certificates** exists.
2. **Content → Elements → Widgets** or your theme’s menu / CMS block.
3. Add a menu item labeled **Tax Exempt Certificates** pointing at the CMS page.

**Change page wording**

Edit **Content → Pages → Tax Exempt Certificates**. Keep the URL key `tax-exempt-certificates` unless you also update the matching Magento layout file (see the module README).

**Optional theme snippet (custom page or popup)**

Only needed if you want the form somewhere the module does not already render. Replace `STORE_BASE_URL`, `CUSTOMER_ID`, and `EMAIL` with Magento values (or use a Magento template that has the logged-in customer).

Popup:

```html
<a href="#" onclick="window.open(
  'https://app.exemptax.com/ecommerce-drop'
    + '?integration_type=adobe_commerce'
    + '&store_base_url=' + encodeURIComponent('https://YOUR-STORE')
    + '&customer_id=CUSTOMER_ID'
    + '&email=' + encodeURIComponent('customer@example.com'),
  'exemptionRequest',
  'left=50,top=100,width=1000,height=600,menubar=0,toolbar=0,location=0,status=0'
); return false;">Click here to submit certificates.</a>
```

Iframe (logged-in customers only):

```html
<iframe
  allow="geolocation; microphone; camera"
  allowfullscreen="true"
  allowtransparency="true"
  frameborder="0"
  scrolling="auto"
  style="min-width: 100%; height: 800px; overflow: auto; border: 0;"
  src="https://app.exemptax.com/ecommerce-drop?integration_type=adobe_commerce&store_base_url=STORE_BASE_URL&customer_id=CUSTOMER_ID&email=EMAIL">
</iframe>
```

Notes:

- The snippet only works when a customer is logged in. Wrap custom placements with Magento customer-session checks, or send visitors to Sign In first.
- Copy-and-paste from PDF/Word can introduce line breaks. If the link fails, confirm the `ecommerce-drop` URL is a single unbroken query string.
- You may change wording and CSS. The required part is the `ecommerce-drop` URL with `integration_type=adobe_commerce`, `store_base_url`, `customer_id`, and `email`.
- EXEMPTAX supports iframe-resizer **4.3.6** for dynamic iframe height. Version 5 of that library has different licensing. If it does not fit your store, use your own resize logic. The default Magento embed uses a fixed 800px height.

## 6. Magento Admin settings

**Stores → Configuration → EXEMPTAX → Integration → General** (website scope):

| Setting | Default | Purpose |
|---|---|---|
| **Apply state-based tax exemptions** | Set on Connect | When Yes, Magento native tax is $0 if the ship-to US state is covered by a valid EXEMPTAX exemption. Use No on TaxJar stores (one checkout tax path). |
| **Enable ecommerce certificate link** | Yes after Connect | When Yes, logged-in customers get My Account iframe and footer links. |

**Stores → Configuration → EXEMPTAX → Settings** is a live mirror of EXEMPTAX Adobe Commerce settings (tax engine, exemption automation, customer groups). Use **Load** / **Save Changes** in that panel, not only the top **Save Config**.

Webhook URL, Settings URL, and ex-key are filled automatically. Do not edit them unless EXEMPTAX support asks you to.

## 7. What customers should see

After Connect & Sync, as a logged-in storefront customer:

1. **My Account** shows the Tax-Exempt Certificates iframe.
2. `/tax-exempt-certificates` shows the CMS instructions plus the same iframe.
3. Footer shows **Tax-Exempt Certificates (New Tab)** and **(Popup)**.
4. Submitting a certificate in EXEMPTAX attaches it to that Magento customer.

Logged out:

- `/tax-exempt-certificates` shows the CMS copy and **Sign In** (no iframe).
- Footer certificate links are hidden.

## 8. User experience and support

The module provides the default My Account, CMS page, and footer placements. Theme or checkout customizations (Hyvä, custom footer, B2B company accounts, checkout messages) should be handled by your Magento team.

We can provide general guidance. We cannot debug custom theme code or advise on specific UI/UX choices. If the module is installed and Activated as described, the default placements should work.

If you believe there is a technical issue with the EXEMPTAX application itself, submit a ticket or email [support@exemptax.com](mailto:support@exemptax.com).
