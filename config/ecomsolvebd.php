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

    /**
     * Order event class names your app dispatches. Defaults are placeholders —
     * map to your domain events (e.g. App\Events\OrderPlaced).
     */
    'order_created_events' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('ECOMSOLVEBD_ORDER_CREATED_EVENTS', '')),
    ))),
];
