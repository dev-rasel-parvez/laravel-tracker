<?php

return [
    /*
    |--------------------------------------------------------------------------
    | EcomSolveBD API
    |--------------------------------------------------------------------------
    */
    'api_base' => rtrim((string) env('ECOMSOLVEBD_API_BASE', 'https://api.ecomsolvebd.com'), '/'),

    /** Tracking public key from Integrations → Laravel (x-merchant-key). */
    'merchant_key' => (string) env('ECOMSOLVEBD_MERCHANT_KEY', ''),

    /** HMAC secret from Integrations → Laravel connect (server-side). */
    'webhook_secret' => (string) env('ECOMSOLVEBD_WEBHOOK_SECRET', ''),

    /** Optional GA4 Measurement ID for Blade gtag base install. */
    'ga4_measurement_id' => (string) env('ECOMSOLVEBD_GA4_MEASUREMENT_ID', ''),

    /** Deploy env header for staging API dual-DB routing (optional). */
    'deploy_env' => (string) env('ECOMSOLVEBD_DEPLOY_ENV', ''),

    /*
    |--------------------------------------------------------------------------
    | First-party tracking (Woo / Shopify / ESB parity)
    |--------------------------------------------------------------------------
    | Auto: same-origin POST /ecomsolvebd/collect until DNS is verified, then
    | custom subdomain CNAME → collect.ecomsolvebd.com.
    | Dashboard Integrations → Laravel can verify DNS; package syncs settings.
    */
    'first_party' => [
        /** auto | custom */
        'mode' => (string) env('ECOMSOLVEBD_FIRST_PARTY_MODE', 'auto'),
        'subdomain_label' => (string) env('ECOMSOLVEBD_TRACKING_SUBDOMAIN_LABEL', 'tracking'),
        'hostname' => (string) env('ECOMSOLVEBD_TRACKING_HOSTNAME', ''),
        'hostname_verified' => filter_var(
            env('ECOMSOLVEBD_TRACKING_HOSTNAME_VERIFIED', false),
            FILTER_VALIDATE_BOOLEAN
        ),
        /** Pull mode/hostname/verified from SaaS (x-merchant-key). */
        'sync_from_saas' => filter_var(env('ECOMSOLVEBD_FIRST_PARTY_SYNC', true), FILTER_VALIDATE_BOOLEAN),
        'sync_ttl_seconds' => (int) env('ECOMSOLVEBD_FIRST_PARTY_SYNC_TTL', 60),
    ],

    /**
     * Order event class names your app dispatches. Defaults are placeholders —
     * map to your domain events (e.g. App\Events\OrderPlaced).
     */
    'order_created_events' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('ECOMSOLVEBD_ORDER_CREATED_EVENTS', '')),
    ))),

    /** Eloquent model used by SaaS → Laravel status push. */
    'order_model' => (string) env('ECOMSOLVEBD_ORDER_MODEL', 'App\\Models\\Order'),

    /** Column that stores the merchant-visible order number (e.g. ORD-…). */
    'order_number_column' => (string) env('ECOMSOLVEBD_ORDER_NUMBER_COLUMN', 'order_number'),

    /*
    |--------------------------------------------------------------------------
    | Product feeds (Woo /feed/*.xml parity)
    |--------------------------------------------------------------------------
    | Public URLs (auto-registered by the package):
    |   /feed/products.xml  — EcomSolveBD catalog sync
    |   /feed/facebook.xml  — Meta Commerce Manager
    |   /feed/tiktok.xml    — TikTok catalog
    |   /feed/google.xml    — Google Merchant Center
    |
    | Cost fields are NOT emitted (Woo parity). SaaS preserves buying/packaging/
    | extra cost columns when syncing from products.xml.
    */
    'feeds' => [
        'enabled' => filter_var(env('ECOMSOLVEBD_FEEDS_ENABLED', true), FILTER_VALIDATE_BOOLEAN),

        /** Optional custom ProductFeedProvider class FQCN. Empty = EloquentProductFeedProvider. */
        'provider' => (string) env('ECOMSOLVEBD_FEED_PROVIDER', ''),

        'currency' => strtoupper(substr((string) env('ECOMSOLVEBD_FEED_CURRENCY', 'BDT'), 0, 3)),
        'brand' => (string) env('ECOMSOLVEBD_FEED_BRAND', ''),
        'store_name' => (string) env('ECOMSOLVEBD_FEED_STORE_NAME', ''),

        'product_model' => (string) env('ECOMSOLVEBD_PRODUCT_MODEL', 'App\\Models\\Product'),

        /**
         * Attribute / accessor map on the product model.
         * Demo shop (laravel-ecommerce-bd): name, final_price, thumbnail_url, stock, slug, brand.
         */
        'columns' => [
            'id' => (string) env('ECOMSOLVEBD_FEED_COL_ID', 'id'),
            'title' => (string) env('ECOMSOLVEBD_FEED_COL_TITLE', 'name'),
            'price' => (string) env('ECOMSOLVEBD_FEED_COL_PRICE', 'final_price'),
            'sku' => (string) env('ECOMSOLVEBD_FEED_COL_SKU', 'sku'),
            'image' => (string) env('ECOMSOLVEBD_FEED_COL_IMAGE', 'thumbnail_url'),
            'stock_qty' => (string) env('ECOMSOLVEBD_FEED_COL_STOCK', 'stock'),
            'in_stock' => (string) env('ECOMSOLVEBD_FEED_COL_IN_STOCK', ''),
            'slug' => (string) env('ECOMSOLVEBD_FEED_COL_SLUG', 'slug'),
            'brand' => (string) env('ECOMSOLVEBD_FEED_COL_BRAND', 'brand'),
            'category' => (string) env('ECOMSOLVEBD_FEED_COL_CATEGORY', ''),
        ],

        /** Only include rows where active_column = active_value (empty column = no filter). */
        'active_column' => (string) env('ECOMSOLVEBD_FEED_ACTIVE_COLUMN', 'is_active'),
        'active_value' => filter_var(env('ECOMSOLVEBD_FEED_ACTIVE_VALUE', true), FILTER_VALIDATE_BOOLEAN),

        /** Optional status column + allow-list (in addition to active_column). */
        'status_column' => (string) env('ECOMSOLVEBD_FEED_STATUS_COLUMN', ''),
        'published_statuses' => array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env('ECOMSOLVEBD_FEED_PUBLISHED_STATUSES', 'published,active')),
        ))),

        /** BelongsTo / BelongsToMany relation name for category labels. */
        'category_relation' => (string) env('ECOMSOLVEBD_FEED_CATEGORY_RELATION', 'category'),
        'category_name_column' => (string) env('ECOMSOLVEBD_FEED_CATEGORY_NAME', 'name'),

        /** Product PDP path — placeholders: {slug}, {id} */
        'product_url_pattern' => (string) env('ECOMSOLVEBD_FEED_PRODUCT_URL', '/product/{slug}'),
    ],
];
