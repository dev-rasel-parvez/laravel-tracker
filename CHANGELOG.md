# Changelog

## v1.0.8

- `_fbp` / `_fbc` (+ `original_fbp` / `original_fbc`) on every Blade collect hit
- Funnel auto-bind (default ON): `view_item`, `add_to_cart`, `remove_from_cart`, `view_cart`, `begin_checkout` via selectors/path heuristics; manual `window.esbTrack(...)` still overrides
- Checkout helpers: `window.esbBindCheckoutForm('#form')` + `data-esb-track="phone_number_added"` (form/field events stay opt-in)
- Admin → SaaS status: Eloquent observer → HMAC `POST /api/v1/orders/channels/laravel/status` (loop-safe; inbound uses quiet save + suppress flag)
- Product feed variation expand: `feeds.variations.relation` (+ column map); empty relation = parent-only (safe default)
- `OrderPayloadFactory` prefers `order_number` column for `idempotencyKey` / external key parity

## v1.0.7

- First-party tracking (Woo/Shopify parity): Auto -> `POST /ecomsolvebd/collect` store proxy -> DNS verify -> custom subdomain (`collect.ecomsolvebd.com`)
- `GET /ecomsolvebd/attribution-config` proxy
- Blade tracker uses FirstParty endpoint ladder; optional SaaS sync (`GET /api/v1/laravel/tracker-config`)

## v1.0.6

- Product feeds (Woo parity): `GET /feed/products.xml`, `/feed/facebook.xml`, `/feed/tiktok.xml`, `/feed/google.xml`
- Configurable Eloquent product map + optional `ProductFeedProvider`
- Cost fields not emitted; SaaS catalog sync preserves merchant costs

## v1.0.5

- SaaS -> Laravel order status: `POST /ecomsolvebd/order-status` (HMAC)

## Earlier

- Tracking collect, GA4 gtag Blade, signed order webhooks
