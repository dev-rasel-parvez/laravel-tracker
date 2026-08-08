<?php

declare(strict_types=1);

namespace EcomSolveBD\LaravelTracker\Feed;

/**
 * Woo class-esb-feed.php XML layouts (products / facebook / tiktok / google).
 */
final class ProductFeedXmlBuilder
{
    /**
     * @param iterable<FeedCatalogProduct> $products
     */
    public static function productsCatalog(iterable $products, string $currency): string
    {
        $currency = strtoupper(substr($currency !== '' ? $currency : 'BDT', 0, 3));
        $out = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $out .= '<products generated_at="' . XmlSafe::escape(gmdate('c')) . '" currency="' . XmlSafe::escape($currency) . '">' . "\n";

        foreach ($products as $p) {
            $out .= "  <product>\n";
            $out .= '    <id>' . XmlSafe::escape($p->id) . "</id>\n";
            $out .= '    <title>' . XmlSafe::escape($p->title) . "</title>\n";
            $out .= '    <price>' . XmlSafe::escape($p->price) . "</price>\n";
            $out .= '    <currency>' . XmlSafe::escape($p->currency !== '' ? $p->currency : $currency) . "</currency>\n";
            $out .= '    <category>' . XmlSafe::escape($p->category) . "</category>\n";
            $out .= '    <image>' . XmlSafe::escape($p->image) . "</image>\n";
            $out .= '    <stock>' . XmlSafe::escape($p->stockStatus) . "</stock>\n";
            $out .= '    <stock_qty>' . XmlSafe::escape($p->stockQty) . "</stock_qty>\n";
            $out .= "  </product>\n";
        }

        $out .= "</products>\n";

        return $out;
    }

    /**
     * @param iterable<FeedChannelItem> $items
     */
    public static function facebookRss(iterable $items, string $storeName, string $origin, string $selfUrl): string
    {
        $store = XmlSafe::escape($storeName !== '' ? $storeName : 'Store');
        $parts = [
            '<?xml version="1.0" encoding="utf-8"?>',
            '<rss version="2.0" xmlns:g="http://base.google.com/ns/1.0" xmlns:atom="http://www.w3.org/2005/Atom">',
            '<channel>',
            '<title>' . $store . ' Product Feed</title>',
            '<description>Product Feed for Facebook</description>',
            '<link>' . XmlSafe::escape(rtrim($origin, '/') . '/') . '</link>',
            '<atom:link href="' . XmlSafe::escape($selfUrl) . '" rel="self" type="application/rss+xml" />',
        ];
        foreach ($items as $it) {
            $parts[] = '<item>';
            $parts[] = '<g:id>' . XmlSafe::escape($it->id) . '</g:id>';
            $parts[] = '<g:title>' . XmlSafe::escape($it->title) . '</g:title>';
            $parts[] = '<g:description>' . XmlSafe::escape($it->description) . '</g:description>';
            $parts[] = '<g:link>' . XmlSafe::escape($it->link) . '</g:link>';
            $parts[] = '<g:image_link>' . XmlSafe::escape($it->imageLink) . '</g:image_link>';
            $parts[] = '<g:brand>' . XmlSafe::escape($it->brand) . '</g:brand>';
            $parts[] = '<g:condition>new</g:condition>';
            $parts[] = '<g:availability>' . XmlSafe::escape($it->availabilityMeta) . '</g:availability>';
            $parts[] = '<g:price>' . XmlSafe::escape($it->price) . '</g:price>';
            $parts[] = '<g:product_type>' . XmlSafe::escape($it->productType) . '</g:product_type>';
            $parts[] = '</item>';
        }
        $parts[] = '</channel>';
        $parts[] = '</rss>';

        return implode("\n", $parts) . "\n";
    }

    /**
     * @param iterable<FeedChannelItem> $items
     */
    public static function tiktok(iterable $items, string $currency): string
    {
        $currency = strtoupper(substr($currency !== '' ? $currency : 'BDT', 0, 3));
        $parts = [
            '<?xml version="1.0" encoding="UTF-8"?>',
            '<products generated_at="' . XmlSafe::escape(gmdate('c')) . '" currency="' . XmlSafe::escape($currency) . '" channel="tiktok">',
        ];
        foreach ($items as $it) {
            $parts[] = '<product>';
            $parts[] = '<sku_id>' . XmlSafe::escape($it->skuId) . '</sku_id>';
            $parts[] = '<id>' . XmlSafe::escape($it->id) . '</id>';
            $parts[] = '<title>' . XmlSafe::escape($it->title) . '</title>';
            $parts[] = '<description>' . XmlSafe::escape($it->description) . '</description>';
            $parts[] = '<availability>' . XmlSafe::escape($it->availabilityMeta) . '</availability>';
            $parts[] = '<condition>new</condition>';
            $parts[] = '<price>' . XmlSafe::escape($it->price) . '</price>';
            $parts[] = '<link>' . XmlSafe::escape($it->link) . '</link>';
            $parts[] = '<image_link>' . XmlSafe::escape($it->imageLink) . '</image_link>';
            $parts[] = '<brand>' . XmlSafe::escape($it->brand) . '</brand>';
            $parts[] = '<product_type>' . XmlSafe::escape($it->productType) . '</product_type>';
            $parts[] = '</product>';
        }
        $parts[] = '</products>';

        return implode("\n", $parts) . "\n";
    }

    /**
     * @param iterable<FeedChannelItem> $items
     */
    public static function googleRss(iterable $items, string $storeName, string $origin): string
    {
        $store = XmlSafe::escape($storeName !== '' ? $storeName : 'Store');
        $parts = [
            '<?xml version="1.0" encoding="UTF-8"?>',
            '<rss version="2.0" xmlns:g="http://base.google.com/ns/1.0">',
            '<channel>',
            '<title>' . $store . ' products</title>',
            '<link>' . XmlSafe::escape(rtrim($origin, '/') . '/') . '</link>',
            '<description>' . $store . ' products</description>',
        ];
        foreach ($items as $it) {
            $parts[] = '<item>';
            $parts[] = '<g:id>' . XmlSafe::escape($it->id) . '</g:id>';
            $parts[] = '<g:title>' . XmlSafe::escape($it->title) . '</g:title>';
            $parts[] = '<g:description>' . XmlSafe::escape($it->description) . '</g:description>';
            $parts[] = '<g:link>' . XmlSafe::escape($it->link) . '</g:link>';
            $parts[] = '<g:image_link>' . XmlSafe::escape($it->imageLink) . '</g:image_link>';
            $parts[] = '<g:availability>' . $it->availabilityGoogle . '</g:availability>';
            $parts[] = '<g:condition>new</g:condition>';
            $parts[] = '<g:price>' . XmlSafe::escape($it->price) . '</g:price>';
            $parts[] = '<g:brand>' . XmlSafe::escape($it->brand) . '</g:brand>';
            $parts[] = '<g:identifier_exists>no</g:identifier_exists>';
            $parts[] = '<g:product_type>' . XmlSafe::escape($it->productType) . '</g:product_type>';
            $parts[] = '</item>';
        }
        $parts[] = '</channel></rss>';

        return implode("\n", $parts) . "\n";
    }
}
