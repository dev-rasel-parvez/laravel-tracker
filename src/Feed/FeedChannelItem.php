<?php

declare(strict_types=1);

namespace EcomSolveBD\LaravelTracker\Feed;

/**
 * Normalized channel row (Meta / TikTok / Google) — Woo load_channel_items shape.
 */
final class FeedChannelItem
{
    public function __construct(
        public readonly string $id,
        public readonly string $skuId,
        public readonly string $title,
        public readonly string $description,
        public readonly string $availabilityMeta,
        public readonly string $availabilityGoogle,
        public readonly string $price,
        public readonly string $link,
        public readonly string $imageLink,
        public readonly string $brand,
        public readonly string $productType,
    ) {
    }
}
