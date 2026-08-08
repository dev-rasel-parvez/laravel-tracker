<?php

declare(strict_types=1);

namespace EcomSolveBD\LaravelTracker\Feed;

/**
 * Optional custom source. Bind via config ecomsolvebd.feeds.provider.
 *
 * @return iterable<FeedCatalogProduct>|iterable<FeedChannelItem>
 */
interface ProductFeedProvider
{
    /** @return iterable<FeedCatalogProduct> */
    public function catalogProducts(): iterable;

    /** @return iterable<FeedChannelItem> */
    public function channelItems(): iterable;
}
