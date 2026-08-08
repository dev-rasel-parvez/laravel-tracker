# ecomsolvebd/laravel-tracker

Composer package for **Laravel** storefronts → [EcomSolveBD](https://ecomsolvebd.com)
(tracking collect + signed order webhooks + status push + product feeds).

**Packagist:** [ecomsolvebd/laravel-tracker](https://packagist.org/packages/ecomsolvebd/laravel-tracker)  
**Source:** https://github.com/dev-rasel-parvez/laravel-tracker  
**Latest:** `v1.0.8`

## Install

```bash
composer require ecomsolvebd/laravel-tracker
php artisan vendor:publish --tag=ecomsolvebd-config
```

Laravel auto-discovers `EcomSolveBdServiceProvider`.

## Connect in EcomSolveBD

1. Dashboard → **Integrations** → **Laravel**
2. Paste store base URL → **Connect**
3. Copy **tracking merchant id** + **webhook secret** into `.env`

```env
ECOMSOLVEBD_API_BASE=https://api.ecomsolvebd.com
ECOMSOLVEBD_MERCHANT_KEY=...
ECOMSOLVEBD_WEBHOOK_SECRET=...
ECOMSOLVEBD_GA4_MEASUREMENT_ID=G-XXXXXXXX
# ECOMSOLVEBD_DEPLOY_ENV=staging

# First-party (optional; default Auto + SaaS sync):
# ECOMSOLVEBD_FIRST_PARTY_MODE=auto
# ECOMSOLVEBD_TRACKING_HOSTNAME=tracking.yourstore.com
# ECOMSOLVEBD_TRACKING_HOSTNAME_VERIFIED=false
# ECOMSOLVEBD_FIRST_PARTY_SYNC=true
```

## First-party tracking (v1.0.7+)

Woo/Shopify parity ladder:

| Mode | Browser collect |
|------|-----------------|
| **Auto** (default, DNS not verified) | Same-origin `POST /ecomsolvebd/collect` (store proxy) |
| **Auto** / **Custom** after DNS verify | `https://tracking.yourdomain.com/api/v1/tracking/collect` |
| Custom without verify | Direct `api.ecomsolvebd.com` fallback |

1. Dashboard → Integrations → Laravel → **First-party tracking**
2. Keep **Auto**, add CNAME `tracking.…` → `collect.ecomsolvebd.com`
3. **Verify DNS now**
4. Package pulls settings via `GET /api/v1/laravel/tracker-config` (cached)

Also auto-registers `GET /ecomsolvebd/attribution-config`.

## Blade layout

```blade
<head>
  @ecomsolvebdGtag
  @ecomsolvebdTracker
</head>
```

- **gtag** — Measurement ID only; GA4 auto-events from Google
- **tracker** — `page_view` → ESB collect (`source: laravel_tracker`) + Meta `_fbp`/`_fbc` cookies when present
- **Funnel (v1.0.8+):** auto-bind ON by default for ecommerce heuristics; form/field events via helper or `data-esb-track`

### Funnel auto vs manual (v1.0.8+)

| Mode | Events |
|------|--------|
| Always auto | `page_view` |
| Auto ON + manual override | `view_item`, `add_to_cart`, `remove_from_cart`, `view_cart`, `begin_checkout` |
| Helper / manual (recommended) | `checkout_form_*`, `*_name_added`, `phone_number_added`, … |

```blade
{{-- config: ECOMSOLVEBD_FUNNEL_AUTO=true (default) --}}
```

```js
// Custom SPA / Livewire — always available:
window.esbTrack?.('add_to_cart', { ecommerce: { currency: 'BDT', value: 999, items: [...] } });

// Bind checkout form once (fires field + form events):
window.esbBindCheckoutForm?.('#checkout-form');

// Or mark inputs:
// <input name="phone" data-esb-track="phone_number_added" />
```

Disable auto heuristics: `ECOMSOLVEBD_FUNNEL_AUTO=false`.

### Funnel event names (SaaS parity)

| Event | Code |
|-------|------|
| View product | `view_item` (alias `view_content` also accepted) |
| Add to cart | `add_to_cart` |
| Remove from cart | `remove_from_cart` |
| View cart | `view_cart` |
| Begin checkout | `begin_checkout` |
| Form started / completed / abandoned | `checkout_form_started` / `checkout_form_completed` / `checkout_form_abandoned` |
| Fields | `first_name_added`, `last_name_added`, `phone_number_added`, `email_address_added`, `address_added`, `city_added`, `state_added`, `postal_code_added`, `country_added` |

Use **Woo/Shopify-style** `ecommerce` (not flat Meta `content_ids`). SaaS maps outbound to Meta / GA4 / TikTok / Google Ads.

```js
window.esbTrack?.('add_to_cart', {
  ecommerce: {
    currency: 'BDT',
    value: 999,
    items: [{ item_id: '123', item_name: 'Product', price: 999, quantity: 1 }],
  },
});
```

Do **not** send raw PII in props. Purchase for ads = order webhook → SaaS Mark shipped / Send purchase (not browser gtag purchase).

## Orders

POST signed JSON to `POST /api/v1/orders/channels/laravel`.

```php
use EcomSolveBD\LaravelTracker\OrderPayloadFactory;
use EcomSolveBD\LaravelTracker\OrderWebhookPoster;

app(OrderWebhookPoster::class)->postOrder(
    OrderPayloadFactory::fromOrder($order)
);
```

Or set `ECOMSOLVEBD_ORDER_CREATED_EVENTS=App\Events\OrderPlaced` (comma-separated) so the service provider listens and posts when the event exposes an `order` property.

### Payload shape (required)

Same as website ingest: `currency`, `trackingUserId`, `customer` (email or phone), `items[]` with `sku`, `title`, `quantity`, `unitPrice`.

Headers:

- `x-merchant-key` — tracking public key
- `x-esb-signature: sha256=<hmac-sha256-hex of raw body>`

## Order status (bidirectional)

### SaaS → Laravel (v1.0.5+)

When a merchant changes order status or books a courier in EcomSolveBD, SaaS POSTs to your shop:

`POST {baseUrl}/ecomsolvebd/order-status`

No custom controller in the merchant app — the package registers the route.

### Laravel admin → SaaS (v1.0.8+)

When local `Order.status` changes (admin / your code), the package observer HMAC-POSTs:

`POST /api/v1/orders/channels/laravel/status`

Disable: `ECOMSOLVEBD_STATUS_OUTBOUND=false`. Loop-safe: SaaS inbound saves quietly and suppresses outbound echo.

### Requirements

1. Same `ECOMSOLVEBD_WEBHOOK_SECRET` as the dashboard (HMAC body signature)
2. Correct shop **base URL** saved under Integrations → Laravel
3. Local order found by `order_number` (default); use the same key in order webhook `idempotencyKey` (`laravel:{order_number}`)

Optional `.env` overrides:

```env
ECOMSOLVEBD_ORDER_MODEL=App\Models\Order
ECOMSOLVEBD_ORDER_NUMBER_COLUMN=order_number
ECOMSOLVEBD_STATUS_OUTBOUND=true
```

### Status map (SaaS ↔ local)

| EcomSolveBD | Laravel `status` |
|-------------|------------------|
| Pending | `pending` |
| Confirmed / Packed / Ready for Pickup / In Transit / … | `confirmed` |
| Delivered | `delivered` |
| Cancelled / Refunded | `cancelled` |
| Returned | `returned` |

Many Laravel shops use fewer stages than SaaS — e.g. “Ready for Pickup” becomes `confirmed` in admin.

### Troubleshoot

| HTTP | Meaning |
|------|---------|
| 401 | Bad / missing HMAC (secret mismatch) |
| 404 | No order with that `order_number` |
| 200 | Status updated |

Merchant guide: https://ecomsolvebd.com/docs/integrations/laravel#status-push

## Product feeds (Woo parity) — v1.0.6+

The package registers the same four public paths as the WooCommerce plugin:

| Path | Use |
|------|-----|
| `/feed/products.xml` | EcomSolveBD catalog sync (Products → feed URL) |
| `/feed/facebook.xml` | Meta Commerce Manager (RSS / `g:` namespace) |
| `/feed/tiktok.xml` | TikTok Ads catalog (`sku_id`) |
| `/feed/google.xml` | Google Merchant Center (`g:identifier_exists=no`) |

No custom shop controllers — `composer update` is enough when your Product model matches the defaults (or you map columns).

### Defaults (common Laravel / demo shop)

| Config | Default |
|--------|---------|
| Model | `App\Models\Product` |
| Title / price / image | `name` / `final_price` / `thumbnail_url` |
| Stock / slug / brand | `stock` / `slug` / `brand` |
| Active filter | `is_active = true` |
| Category | relation `category` → `name` |
| PDP URL | `/product/{slug}` |

### `.env` examples

```env
ECOMSOLVEBD_FEEDS_ENABLED=true
ECOMSOLVEBD_FEED_CURRENCY=BDT
ECOMSOLVEBD_FEED_BRAND=
ECOMSOLVEBD_FEED_STORE_NAME=
ECOMSOLVEBD_PRODUCT_MODEL=App\Models\Product
ECOMSOLVEBD_FEED_PRODUCT_URL=/product/{slug}
ECOMSOLVEBD_FEED_COL_PRICE=final_price
ECOMSOLVEBD_FEED_COL_IMAGE=thumbnail_url
ECOMSOLVEBD_FEED_CATEGORY_RELATION=category
# Variations (optional): ECOMSOLVEBD_FEED_VARIATIONS_RELATION=variants
```

### Variations (v1.0.8+)

Empty `ECOMSOLVEBD_FEED_VARIATIONS_RELATION` = parent rows only (safe default). When set to a HasMany relation (e.g. `variants`), each child becomes a feed row (`parentId-variantId`); parent is skipped if children exist.

```env
ECOMSOLVEBD_FEED_VARIATIONS_RELATION=variants
ECOMSOLVEBD_FEED_VARIANT_TITLE=name
ECOMSOLVEBD_FEED_VARIANT_PRICE=price
ECOMSOLVEBD_FEED_VARIANT_SKU=sku
ECOMSOLVEBD_FEED_VARIANT_STOCK=stock
```

Custom shops: publish config and edit `feeds.columns` / `product_url_pattern`, or bind a class implementing `ProductFeedProvider`.

### Merchant checklist

1. Package ≥ **v1.0.8** on the live Laravel site (first-party ≥ v1.0.7; feeds ≥ v1.0.6)
2. Open `https://yoursite.com/feed/products.xml` — should return XML
3. Dashboard → Products → paste the four URLs (or catalog URL for SaaS sync)
4. Meta / TikTok / Google: paste facebook / tiktok / google URLs into each platform’s catalog feed UI
5. Cost fields (`buying_cost`, etc.) stay in SaaS only — feeds do **not** overwrite them (Woo parity)
6. First-party: Integrations → Laravel → Auto + optional CNAME verify

Merchant guide: https://ecomsolvebd.com/docs/integrations/laravel#product-feeds · https://ecomsolvebd.com/docs/integrations/laravel#first-party

## Docs

Full guide: https://ecomsolvebd.com/docs/integrations/laravel

## Limits

- Product CRUD stays in Laravel (connected-store rule)
- Channel feeds skip items without a public `http(s)` image or price ≤ 0 (Woo parity)
- Variation expand is not automatic — implement `ProductFeedProvider` if you need one row per variant
- GTIN/MPN not emitted (`identifier_exists=no` on Google), same as Woo plugin
