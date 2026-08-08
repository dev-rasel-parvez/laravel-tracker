<?php

declare(strict_types=1);

namespace EcomSolveBD\LaravelTracker\Feed;

use Illuminate\Database\Eloquent\Model;

/**
 * Default feed source: Eloquent Product model + configurable column map.
 * Defaults match common Laravel shops (name/price/sku/stock/slug/is_active/category).
 */
final class EloquentProductFeedProvider implements ProductFeedProvider
{
    public function catalogProducts(): iterable
    {
        foreach ($this->eachModel() as $model) {
            $row = $this->mapCatalog($model);
            if ($row !== null) {
                yield $row;
            }
        }
    }

    public function channelItems(): iterable
    {
        foreach ($this->eachModel() as $model) {
            $row = $this->mapChannel($model);
            if ($row !== null) {
                yield $row;
            }
        }
    }

    /** @return \Generator<int, Model> */
    private function eachModel(): \Generator
    {
        $class = (string) config('ecomsolvebd.feeds.product_model', 'App\\Models\\Product');
        if (! class_exists($class) || ! is_subclass_of($class, Model::class)) {
            return;
        }

        $with = [];
        $catRel = (string) config('ecomsolvebd.feeds.category_relation', '');
        if ($catRel !== '') {
            $with[] = $catRel;
        }

        /** @var Model $probe */
        $probe = new $class();
        $query = $class::query()->orderBy($probe->getKeyName());
        if ($with !== []) {
            $query->with($with);
        }

        $activeCol = (string) config('ecomsolvebd.feeds.active_column', '');
        if ($activeCol !== '') {
            $activeVal = config('ecomsolvebd.feeds.active_value', true);
            $query->where($activeCol, $activeVal);
        }

        $statusCol = (string) config('ecomsolvebd.feeds.status_column', '');
        if ($statusCol !== '') {
            $statuses = config('ecomsolvebd.feeds.published_statuses', ['published', 'active']);
            if (is_array($statuses) && $statuses !== []) {
                $query->whereIn($statusCol, $statuses);
            }
        }

        foreach ($query->cursor() as $model) {
            yield $model;
        }
    }

    private function mapCatalog(Model $model): ?FeedCatalogProduct
    {
        $cols = $this->columns();
        $id = $this->attr($model, $cols['id'] ?? 'id');
        $title = XmlSafe::stripHtml((string) $this->attr($model, $cols['title'] ?? 'name'));
        if ($id === '' || $title === '') {
            return null;
        }

        $currency = $this->currency();
        $priceNum = $this->priceNumber($model, $cols);
        $price = $priceNum !== null ? (string) $priceNum : (string) $this->attr($model, $cols['price'] ?? 'price');

        $image = $this->resolveImageUrl($model, $cols);
        $stock = $this->stockFields($model, $cols);
        $category = $this->categoryLabel($model, ', ');

        return new FeedCatalogProduct(
            id: $id,
            title: $title,
            price: $price,
            currency: $currency,
            category: $category,
            image: $image,
            stockStatus: $stock['status'],
            stockQty: $stock['qty'],
        );
    }

    private function mapChannel(Model $model): ?FeedChannelItem
    {
        $cols = $this->columns();
        $id = XmlSafe::text((string) $this->attr($model, $cols['id'] ?? 'id'), 100);
        $title = XmlSafe::text((string) $this->attr($model, $cols['title'] ?? 'name'), 150);
        if ($id === '' || $title === '') {
            return null;
        }

        $image = $this->resolveImageUrl($model, $cols);
        if (! XmlSafe::isPublicHttpImage($image)) {
            return null;
        }

        $priceNum = $this->priceNumber($model, $cols);
        if ($priceNum === null || $priceNum <= 0) {
            return null;
        }

        $currency = $this->currency();
        $sku = (string) $this->attr($model, $cols['sku'] ?? 'sku');
        if ($sku === '') {
            $sku = $id;
        }

        $storeBrand = $this->brand($model, $cols);
        $inStock = $this->isInStock($model, $cols);
        $link = $this->productUrl($model, $cols);
        $ptype = $this->categoryLabel($model, ' > ');
        if ($ptype === '') {
            $ptype = 'General';
        }
        $ptype = XmlSafe::text($ptype, 750);

        return new FeedChannelItem(
            id: $id,
            skuId: XmlSafe::text($sku, 100),
            title: $title,
            description: XmlSafe::text($title . ' — available at ' . $storeBrand, 5000),
            availabilityMeta: $inStock ? 'in stock' : 'out of stock',
            availabilityGoogle: $inStock ? 'in_stock' : 'out_of_stock',
            price: number_format($priceNum, 2, '.', '') . ' ' . $currency,
            link: $link,
            imageLink: substr($image, 0, 2000),
            brand: XmlSafe::text($storeBrand, 70),
            productType: $ptype,
        );
    }

    /** @return array<string, string> */
    private function columns(): array
    {
        $cols = config('ecomsolvebd.feeds.columns', []);

        return is_array($cols) ? array_map('strval', $cols) : [];
    }

    private function currency(): string
    {
        $c = (string) config('ecomsolvebd.feeds.currency', 'BDT');

        return strtoupper(substr($c !== '' ? $c : 'BDT', 0, 3));
    }

    private function brand(Model $model, array $cols): string
    {
        $configured = trim((string) config('ecomsolvebd.feeds.brand', ''));
        if ($configured !== '') {
            return $configured;
        }
        $col = $cols['brand'] ?? '';
        if ($col !== '') {
            $fromProduct = trim((string) $this->attr($model, $col));
            if ($fromProduct !== '') {
                return $fromProduct;
            }
        }
        $store = trim((string) config('ecomsolvebd.feeds.store_name', ''));
        if ($store !== '') {
            return $store;
        }
        $app = trim((string) config('app.name', ''));

        return $app !== '' ? $app : 'Store';
    }

    private function attr(Model $model, string $key): mixed
    {
        if ($key === '') {
            return null;
        }
        // Support nested: category.name via relation path is handled separately.
        if (str_contains($key, '.')) {
            return data_get($model, $key);
        }

        return $model->getAttribute($key);
    }

    private function priceNumber(Model $model, array $cols): ?float
    {
        $raw = $this->attr($model, $cols['price'] ?? 'price');
        if ($raw === null || $raw === '') {
            return null;
        }
        $n = (float) $raw;

        return is_finite($n) ? $n : null;
    }

    private function resolveImageUrl(Model $model, array $cols): string
    {
        $raw = trim((string) ($this->attr($model, $cols['image'] ?? 'image') ?? ''));
        if ($raw === '') {
            return '';
        }
        if (preg_match('#^https?://#i', $raw)) {
            return $raw;
        }
        // Relative storage path → absolute URL
        try {
            if (function_exists('url')) {
                return (string) url(ltrim($raw, '/'));
            }
        } catch (\Throwable) {
            // ignore
        }
        $base = rtrim((string) config('app.url', ''), '/');

        return $base !== '' ? $base . '/' . ltrim($raw, '/') : $raw;
    }

    /** @return array{status: string, qty: string} */
    private function stockFields(Model $model, array $cols): array
    {
        if (! $this->isInStock($model, $cols)) {
            return ['status' => 'out_of_stock', 'qty' => '0'];
        }

        $qtyCol = $cols['stock_qty'] ?? '';
        if ($qtyCol === '') {
            return ['status' => 'in_stock', 'qty' => 'unlimited'];
        }

        $raw = $this->attr($model, $qtyCol);
        if ($raw === null || $raw === '') {
            return ['status' => 'in_stock', 'qty' => 'unlimited'];
        }

        $qty = max(0, (int) $raw);

        return ['status' => 'in_stock', 'qty' => (string) $qty];
    }

    private function isInStock(Model $model, array $cols): bool
    {
        $boolCol = $cols['in_stock'] ?? '';
        if ($boolCol !== '') {
            return (bool) $this->attr($model, $boolCol);
        }

        $qtyCol = $cols['stock_qty'] ?? '';
        if ($qtyCol === '') {
            return true;
        }

        $raw = $this->attr($model, $qtyCol);
        if ($raw === null || $raw === '') {
            return true;
        }

        return (int) $raw > 0;
    }

    private function categoryLabel(Model $model, string $join): string
    {
        $rel = (string) config('ecomsolvebd.feeds.category_relation', '');
        $nameCol = (string) config('ecomsolvebd.feeds.category_name_column', 'name');
        if ($rel !== '') {
            $related = $model->getRelationValue($rel);
            if ($related instanceof Model) {
                return trim((string) $related->getAttribute($nameCol));
            }
            if (is_iterable($related)) {
                $names = [];
                foreach ($related as $row) {
                    if ($row instanceof Model) {
                        $n = trim((string) $row->getAttribute($nameCol));
                        if ($n !== '') {
                            $names[] = $n;
                        }
                    }
                }

                return implode($join, $names);
            }
        }

        $cols = $this->columns();
        $flat = $cols['category'] ?? '';
        if ($flat !== '') {
            return trim((string) $this->attr($model, $flat));
        }

        return '';
    }

    private function productUrl(Model $model, array $cols): string
    {
        $pattern = (string) config('ecomsolvebd.feeds.product_url_pattern', '/product/{slug}');
        $slug = (string) $this->attr($model, $cols['slug'] ?? 'slug');
        $id = (string) $this->attr($model, $cols['id'] ?? 'id');
        $path = str_replace(
            ['{slug}', '{id}'],
            [$slug !== '' ? $slug : $id, $id],
            $pattern,
        );
        if (preg_match('#^https?://#i', $path)) {
            return $path;
        }
        $base = rtrim((string) config('app.url', ''), '/');

        return $base . '/' . ltrim($path, '/');
    }
}
