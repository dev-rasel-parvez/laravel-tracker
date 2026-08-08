<?php

declare(strict_types=1);

namespace EcomSolveBD\LaravelTracker;

/**
 * Best-effort mapping from common Laravel order event shapes → ESB website ingest DTO.
 */
final class OrderPayloadFactory
{
    /**
     * @return array<string, mixed>|null
     */
    public static function fromEvent(object $event): ?array
    {
        $order = null;
        foreach (['order', 'Order', 'model'] as $prop) {
            if (isset($event->{$prop}) && is_object($event->{$prop})) {
                $order = $event->{$prop};
                break;
            }
        }
        if ($order === null && method_exists($event, 'order')) {
            /** @var mixed $maybe */
            $maybe = $event->order();
            if (is_object($maybe)) {
                $order = $maybe;
            }
        }
        if ($order === null) {
            return null;
        }

        return self::fromOrder($order);
    }

    /**
     * @return array<string, mixed>
     */
    public static function fromOrder(object $order): array
    {
        $numberCol = (string) config('ecomsolvebd.order_number_column', 'order_number');
        $id =
            self::str($order, array_values(array_unique(array_filter([$numberCol, 'order_number', 'number', 'id', 'uuid']))))
            ?? (string) time();
        $currency = strtoupper(self::str($order, ['currency', 'currency_code']) ?? 'BDT');
        $email = self::nestedStr($order, ['customer.email', 'user.email', 'email']);
        $phone = self::nestedStr($order, ['customer.phone', 'user.phone', 'phone']);
        $name = self::nestedStr($order, ['customer.name', 'customer.full_name', 'customer_name', 'billing_name']);

        $items = [];
        $rawItems = null;
        foreach (['items', 'lines', 'lineItems', 'products'] as $key) {
            if (isset($order->{$key})) {
                $rawItems = $order->{$key};
                break;
            }
        }
        if (is_iterable($rawItems)) {
            foreach ($rawItems as $row) {
                if (!is_object($row) && !is_array($row)) {
                    continue;
                }
                $r = (object) $row;
                $qty = (int) (self::str($r, ['quantity', 'qty']) ?? 1);
                $unit = self::str($r, ['unit_price', 'price', 'unitPrice']) ?? '0';
                $items[] = [
                    'sku' => (self::str($r, ['sku', 'product_sku']) ?? 'item'),
                    'title' => (self::str($r, ['title', 'name', 'product_name']) ?? 'Item'),
                    'productId' => self::str($r, ['product_id', 'productId']),
                    'variantTitle' => self::str($r, ['variant_title', 'variantTitle']),
                    'quantity' => max(1, $qty),
                    'unitPrice' => $unit,
                ];
            }
        }
        if ($items === []) {
            $items[] = [
                'sku' => 'order',
                'title' => 'Order ' . $id,
                'quantity' => 1,
                'unitPrice' => self::str($order, ['total', 'grand_total', 'amount']) ?? '0',
            ];
        }

        $visitor = self::str($order, ['tracking_user_id', 'visitor_key', 'esb_visitor_key'])
            ?? self::browserVisitorId()
            // Underscore marks synthetic keys so Core never treats them as browser visitors.
            ?? ('lv_' . substr(hash('sha256', $id . ($email ?? '') . ($phone ?? '')), 0, 14));

        $payload = [
            'currency' => $currency,
            'trackingUserId' => $visitor,
            'idempotencyKey' => 'laravel:' . $id,
            'customer' => array_filter([
                'email' => $email,
                'phone' => $phone,
                'fullName' => $name,
            ]),
            'items' => $items,
            'taxTotal' => self::str($order, ['tax_total', 'taxTotal']),
            'shippingTotal' => self::str($order, ['shipping_total', 'shippingTotal']),
            'discountTotal' => self::str($order, ['discount_total', 'discountTotal']),
            'notes' => self::str($order, ['notes', 'note']),
            'district' => self::str($order, ['district', 'shipping_district']),
            'upazila' => self::str($order, ['upazila', 'thana', 'shipping_upazila']),
            'address' => self::str($order, ['address', 'shipping_address', 'full_address']),
        ];

        if (empty($payload['customer']['email']) && empty($payload['customer']['phone'])) {
            $payload['customer']['phone'] = '00000000000';
        }

        return array_filter(
            $payload,
            static fn ($v) => $v !== null && $v !== '',
        );
    }

    /**
     * Read JS-set esb_vid even when Laravel EncryptCookies would drop request()->cookie().
     */
    private static function browserVisitorId(): ?string
    {
        $raw = null;
        if (isset($_COOKIE['esb_vid']) && is_string($_COOKIE['esb_vid'])) {
            $raw = $_COOKIE['esb_vid'];
        }
        if ($raw === null || $raw === '') {
            return null;
        }
        try {
            $raw = rawurldecode($raw);
        } catch (\Throwable) {
            /* keep raw */
        }
        $raw = strtolower(trim($raw));
        if (!preg_match('/^[a-z0-9]{4,32}$/', $raw)) {
            return null;
        }
        return $raw;
    }

    /** @param list<string> $keys */
    private static function str(object $o, array $keys): ?string
    {
        foreach ($keys as $k) {
            if (isset($o->{$k}) && (is_string($o->{$k}) || is_numeric($o->{$k}))) {
                $t = trim((string) $o->{$k});
                if ($t !== '') {
                    return $t;
                }
            }
        }
        return null;
    }

    /** @param list<string> $paths dot paths */
    private static function nestedStr(object $o, array $paths): ?string
    {
        foreach ($paths as $path) {
            $cur = $o;
            $ok = true;
            foreach (explode('.', $path) as $seg) {
                if (is_object($cur) && isset($cur->{$seg})) {
                    $cur = $cur->{$seg};
                } elseif (is_array($cur) && array_key_exists($seg, $cur)) {
                    $cur = $cur[$seg];
                } else {
                    $ok = false;
                    break;
                }
            }
            if ($ok && (is_string($cur) || is_numeric($cur))) {
                $t = trim((string) $cur);
                if ($t !== '') {
                    return $t;
                }
            }
        }
        return null;
    }
}
