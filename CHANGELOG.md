# Changelog

## v1.0.6

- Product feeds (Woo parity): `GET /feed/products.xml`, `/feed/facebook.xml`, `/feed/tiktok.xml`, `/feed/google.xml`
- Configurable Eloquent product map + optional `ProductFeedProvider`
- Cost fields not emitted; SaaS catalog sync preserves merchant costs

## v1.0.5

- SaaS → Laravel order status: `POST /ecomsolvebd/order-status` (HMAC)

## Earlier

- Tracking collect, GA4 gtag Blade, signed order webhooks
