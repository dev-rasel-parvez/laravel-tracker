<?php

declare(strict_types=1);

namespace EcomSolveBD\LaravelTracker\Http;

use EcomSolveBD\LaravelTracker\OrderStatusOutbound;
use EcomSolveBD\LaravelTracker\WebhookSignature;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

/**
 * Inbound: SaaS Dashboard Create Order → Laravel admin row.
 * POST /ecomsolvebd/order-create
 */
final class OrderCreateInboundController extends Controller
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

        $modelClass = (string) config('ecomsolvebd.order_model', 'App\\Models\\Order');
        if (! class_exists($modelClass) || ! is_subclass_of($modelClass, Model::class)) {
            return response()->json(['ok' => false, 'error' => 'order_model_missing'], 500);
        }

        $numberColumn = (string) config('ecomsolvebd.order_number_column', 'order_number');
        /** @var Model|null $existing */
        $existing = $modelClass::query()->where($numberColumn, $orderNumber)->first();
        if ($existing !== null) {
            return response()->json([
                'ok' => true,
                'unchanged' => true,
                'orderNumber' => $orderNumber,
            ]);
        }

        $cols = (array) config('ecomsolvebd.order_create.columns', []);
        $customer = is_array($data['customer'] ?? null) ? $data['customer'] : [];
        $items = is_array($data['items'] ?? null) ? $data['items'] : [];
        $firstItem = is_array($items[0] ?? null) ? $items[0] : [];

        $fullName = trim((string) ($customer['fullName'] ?? $customer['full_name'] ?? ''));
        $phone = trim((string) ($customer['phone'] ?? ''));
        $email = trim((string) ($customer['email'] ?? ''));
        $district = trim((string) ($data['district'] ?? ''));
        $upazila = trim((string) ($data['upazila'] ?? ''));
        $address = trim((string) ($data['address'] ?? ''));
        $notes = trim((string) ($data['notes'] ?? ''));
        $currency = strtoupper(substr((string) ($data['currency'] ?? 'BDT'), 0, 3));
        $shipping = (string) ($data['shippingTotal'] ?? $data['shipping_total'] ?? '0');
        $unitPrice = (string) ($firstItem['unitPrice'] ?? $firstItem['unit_price'] ?? '0');
        $qty = max(1, (int) ($firstItem['quantity'] ?? 1));
        $lineTotal = number_format(((float) $unitPrice) * $qty, 2, '.', '');
        $total = number_format(((float) $lineTotal) + ((float) $shipping), 2, '.', '');
        $productTitle = trim((string) ($firstItem['title'] ?? $firstItem['name'] ?? 'Item'));
        $sku = trim((string) ($firstItem['sku'] ?? ''));

        $status = 'pending';
        $allowed = [];
        if (defined($modelClass . '::STATUSES') && is_array($modelClass::STATUSES)) {
            $allowed = array_map('strval', array_keys($modelClass::STATUSES));
            if ($allowed !== [] && ! in_array($status, $allowed, true)) {
                $status = $allowed[0];
            }
        }

        $attrs = [
            $numberColumn => $orderNumber,
        ];

        $map = [
            'customer_name' => $fullName !== '' ? $fullName : 'Customer',
            'customer_phone' => $phone,
            'customer_email' => $email !== '' ? $email : null,
            'district' => $district !== '' ? $district : null,
            'upazila' => $upazila !== '' ? $upazila : null,
            'address' => $address !== '' ? $address : null,
            'notes' => $notes !== '' ? $notes : 'Created from EcomSolveBD',
            'currency' => $currency,
            'subtotal' => $lineTotal,
            'shipping_total' => number_format((float) $shipping, 2, '.', ''),
            'total' => $total,
            'status' => $status,
            'product_title' => $productTitle,
            'sku' => $sku !== '' ? $sku : null,
            'quantity' => $qty,
            'unit_price' => number_format((float) $unitPrice, 2, '.', ''),
            'items_json' => json_encode($items, JSON_UNESCAPED_UNICODE),
            'source' => 'ecomsolvebd_manual',
            'status_changed_by' => 'EcomSolveBD',
        ];

        foreach ($map as $logical => $value) {
            if ($value === null) {
                continue;
            }
            $column = (string) ($cols[$logical] ?? $logical);
            if ($column === '' || $column === '0' || $column === 'false') {
                continue;
            }
            /** @var Model $probe */
            $probe = new $modelClass();
            if (method_exists($probe, 'isFillable') && ! $probe->isFillable($column)) {
                // Still try forceFill for guarded models that allow mass assignment via forceFill.
            }
            $attrs[$column] = $value;
        }

        if (! isset($attrs['id']) && method_exists($modelClass, 'bootSoftDeletes') === false) {
            // leave id to DB / model defaults
        }

        OrderStatusOutbound::$suppressOutbound = true;
        try {
            /** @var Model $order */
            $order = new $modelClass();
            if (method_exists($order, 'forceFill')) {
                $order->forceFill($attrs);
            } else {
                foreach ($attrs as $k => $v) {
                    $order->setAttribute($k, $v);
                }
            }
            if (method_exists($order, 'saveQuietly')) {
                $order->saveQuietly();
            } else {
                $order->save();
            }
        } finally {
            OrderStatusOutbound::$suppressOutbound = false;
        }

        return response()->json([
            'ok' => true,
            'orderNumber' => $orderNumber,
            'id' => $order->getKey(),
            'created' => true,
        ], 201);
    }
}
