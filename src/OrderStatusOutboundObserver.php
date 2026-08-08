<?php

declare(strict_types=1);

namespace EcomSolveBD\LaravelTracker;

use Illuminate\Database\Eloquent\Model;

/**
 * Eloquent observer: when local order status changes → SaaS (Admin→SaaS).
 */
final class OrderStatusOutboundObserver
{
    public function updated(Model $order): void
    {
        if (OrderStatusOutbound::$suppressOutbound) {
            return;
        }
        if (! filter_var(config('ecomsolvebd.status_outbound.enabled', true), FILTER_VALIDATE_BOOLEAN)) {
            return;
        }
        if (! $order->wasChanged('status')) {
            return;
        }
        try {
            OrderStatusOutbound::push($order);
        } catch (\Throwable) {
            // never break admin save
        }
    }
}
