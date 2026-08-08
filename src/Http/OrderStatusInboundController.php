<?php

declare(strict_types=1);

namespace EcomSolveBD\LaravelTracker\Http;

use EcomSolveBD\LaravelTracker\OrderStatusMapper;
use EcomSolveBD\LaravelTracker\WebhookSignature;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * Inbound: SaaS → Laravel order status push (Woo pushStorefront parity).
 * POST /ecomsolvebd/order-status
 */
final class OrderStatusInboundController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $secret = (string) config('ecomsolvebd.webhook_secret', '');
        $raw = $request->getContent();
        $sig = $request->header('x-esb-signature')
            ?? $request->header('x-webhook-signature')
            ?? '';

        if ($secret === '' || ! WebhookSignature::isValid($raw, $secret, $sig)) {
            return response()->json(['ok' => false, 'error' => 'invalid_signature'], 401);
        }

        /** @var array<string, mixed> $data */
        $data = json_decode($raw, true) ?? [];
        $orderNumber = trim((string) ($data['orderNumber'] ?? $data['order_number'] ?? ''));
        if ($orderNumber === '') {
            return response()->json(['ok' => false, 'error' => 'order_number_required'], 422);
        }

        $statusRaw = (string) ($data['status'] ?? $data['masterStatus'] ?? $data['master_status'] ?? '');
        if ($statusRaw === '') {
            return response()->json(['ok' => false, 'error' => 'status_required'], 422);
        }

        $modelClass = (string) config('ecomsolvebd.order_model', 'App\\Models\\Order');
        if (! class_exists($modelClass) || ! is_subclass_of($modelClass, Model::class)) {
            return response()->json(['ok' => false, 'error' => 'order_model_missing'], 500);
        }

        $numberColumn = (string) config('ecomsolvebd.order_number_column', 'order_number');
        /** @var Model|null $order */
        $order = $modelClass::query()->where($numberColumn, $orderNumber)->first();
        if ($order === null) {
            return response()->json(['ok' => false, 'error' => 'order_not_found', 'orderNumber' => $orderNumber], 404);
        }

        $allowed = [];
        if (defined($modelClass . '::STATUSES') && is_array($modelClass::STATUSES)) {
            $allowed = array_map('strval', array_keys($modelClass::STATUSES));
        }
        $localStatus = OrderStatusMapper::toLocalStatus($statusRaw, $allowed);

        $current = (string) ($order->getAttribute('status') ?? '');
        if ($current === $localStatus) {
            return response()->json(['ok' => true, 'unchanged' => true, 'status' => $localStatus]);
        }

        // Trusted SaaS push may jump stages (Pending → Delivered); bypass app transition rules.
        $attrs = [
            'status' => $localStatus,
            'status_changed_by' => 'EcomSolveBD',
            'status_changed_at' => now(),
        ];

        $courier = $data['courierCode'] ?? $data['courier_code'] ?? null;
        $tracking = $data['trackingNumber'] ?? $data['tracking_number'] ?? null;
        if (is_string($courier) && $courier !== '' && $order->isFillable('courier_status')) {
            $attrs['courier_status'] = $courier;
        }
        if (is_string($tracking) && $tracking !== '' && $order->isFillable('courier_tracking_code')) {
            $attrs['courier_tracking_code'] = $tracking;
        }

        $order->forceFill($attrs)->save();

        return response()->json([
            'ok' => true,
            'orderNumber' => $orderNumber,
            'status' => $localStatus,
            'previous' => $current,
        ]);
    }
}
