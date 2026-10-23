# Changelog

## v1.0.13

- Every event now carries `device`, `device_model`, `browser`, `os` from the shopper's user-agent (same labels as the WooCommerce tracker), so Device & technology reports and the Device / OS with Version / Device model / Browser filters fill in for Laravel stores.

## v1.0.12

- Collect proxy forwards the shopper as `X-ESB-Client-IP` / `X-ESB-Client-Country`. Before, the API saw the hosting server and every visitor was geolocated to the server country.

## v1.0.11

- `esbBindCheckoutForm` and `data-esb-track`: `first_name_added` now sends the typed name as `{ name }` and `last_name_added` as `{ last_name }`, so Checkout drop-off (abandon recovery) shows the buyer name. Stored only in EcomSolveBD; not forwarded to GA4 / ads.

## v1.0.10

- `@ecomsolvebdTracker`: visitor's first-ever load (no `esb_first_touch` in localStorage yet) now sends `landing_page_view` instead of `page_view`, so Dashboard Total Users / Campaigns LP Views and order journeys count Laravel visits (same as Custom bootstrap and Woo/Shopify trackers). Once per visitor: that hit never also sends `page_view`, later loads send only `page_view`, and the 2s GA-cookie re-hit repeats the same event (server ingest dedupe merges it, no extra row).

## v1.0.9

- SaaS Dashboard **Create Order** → Laravel: HMAC `POST /ecomsolvebd/order-create` (configurable column map; outbound suppress so it does not re-webhook SaaS)
- Docs: Create Order two-way note for Laravel package merchants

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
