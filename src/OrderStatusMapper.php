<?php

declare(strict_types=1);

namespace EcomSolveBD\LaravelTracker;

/**
 * Maps EcomSolveBD master / storefront status → local order status string.
 * Default targets common BD Laravel shops: pending|confirmed|delivered|cancelled|returned.
 */
final class OrderStatusMapper
{
    /**
     * @param list<string> $allowed Local statuses the app accepts
     */
    public static function toLocalStatus(string $incoming, array $allowed = []): string
    {
        $k = strtolower(trim(str_replace([' ', '-'], '_', $incoming)));
        $map = [
            'pending' => 'pending',
            'order_created' => 'pending',
            'confirmed' => 'confirmed',
            'order_confirmed' => 'confirmed',
            'packed' => 'confirmed',
            'ready_for_pickup' => 'confirmed',
            'picked_up' => 'confirmed',
            'in_transit' => 'confirmed',
            'out_for_delivery' => 'confirmed',
            'shipped' => 'confirmed',
            'delivered' => 'delivered',
            'completed' => 'delivered',
            'cancelled' => 'cancelled',
            'canceled' => 'cancelled',
            'refunded' => 'cancelled',
            'returned' => 'returned',
        ];
        $local = $map[$k] ?? $k;
        if ($allowed !== [] && ! in_array($local, $allowed, true)) {
            // Prefer confirmed as safe mid-funnel if unknown fine-grained status arrives.
            if (in_array('confirmed', $allowed, true) && ! in_array($local, ['cancelled', 'returned', 'delivered', 'pending'], true)) {
                return 'confirmed';
            }
        }
        return $local;
    }
}
