<?php

declare(strict_types=1);

namespace EcomSolveBD\LaravelTracker\Feed;

/**
 * Normalized catalog row for products.xml (SaaS sync) — Woo render_products_catalog_xml shape.
 * Cost fields are intentionally omitted (Woo parity); SaaS preserves merchant cost columns on sync.
 */
final class FeedCatalogProduct
{
    public function __construct(
        public readonly string $id,
        public readonly string $title,
        public readonly string $price,
        public readonly string $currency,
        public readonly string $category,
        public readonly string $image,
        public readonly string $stockStatus,
        public readonly string $stockQty,
    ) {
    }
}
