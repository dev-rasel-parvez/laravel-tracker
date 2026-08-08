# ecomsolvebd/laravel-tracker

Composer package for **Laravel** storefronts → [EcomSolveBD](https://ecomsolvebd.com) (tracking collect + signed order webhooks).

**Packagist:** [ecomsolvebd/laravel-tracker](https://packagist.org/packages/ecomsolvebd/laravel-tracker)  
**Source:** https://github.com/dev-rasel-parvez/laravel-tracker  
**Latest:** `v1.0.3`

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
# Optional staging dual-DB:
# ECOMSOLVEBD_DEPLOY_ENV=staging
```

## Blade layout

```blade
<head>
  @ecomsolvebdGtag
  @ecomsolvebdTracker
</head>
```

- **gtag** — Measurement ID only; GA4 auto-events from Google
- **tracker** — `page_view` → ESB collect (`source: laravel_tracker`)
- Funnel events are **not** automatic — call `window.esbTrack(...)` from your Blade/JS (see marketing docs `/docs/integrations/laravel` → **ফানেল ইভেন্ট**)

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

## Docs

Full guide: https://ecomsolvebd.com/docs/integrations/laravel

## MVP limits

- No two-way order status push yet
- No product feeds yet
- Product CRUD stays in Laravel (connected-store rule)
