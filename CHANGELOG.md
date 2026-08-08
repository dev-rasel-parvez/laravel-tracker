# Changelog

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
