<?php

declare(strict_types=1);

namespace EcomSolveBD\LaravelTracker;

/**
 * Laravel admin → SaaS order status (HMAC).
 * Loop guard: OrderStatusOutbound::$suppressOutbound during SaaS inbound.
 */
final class OrderStatusOutbound
{
    /** Set while applying SaaS → Laravel inbound so observers never echo back. */
    public static bool $suppressOutbound = false;

    /**
     * @param object $order Eloquent order (or any object with order_number/status)
     * @return array{ok:bool,status:int,body:mixed,skipped?:string}
     */
    public static function push(object $order): array
    {
        if (self::$suppressOutbound) {
            return ['ok' => true, 'status' => 0, 'body' => null, 'skipped' => 'saas_inbound'];
        }

        if (! filter_var(config('ecomsolvebd.status_outbound.enabled', true), FILTER_VALIDATE_BOOLEAN)) {
            return ['ok' => true, 'status' => 0, 'body' => null, 'skipped' => 'disabled'];
        }

        $numberCol = (string) config('ecomsolvebd.order_number_column', 'order_number');
        $orderNumber = '';
        if (isset($order->{$numberCol})) {
            $orderNumber = trim((string) $order->{$numberCol});
        }
        if ($orderNumber === '' && isset($order->order_number)) {
            $orderNumber = trim((string) $order->order_number);
        }
        if ($orderNumber === '') {
            return ['ok' => false, 'status' => 0, 'body' => null, 'skipped' => 'missing_order_number'];
        }

        $status = isset($order->status) ? strtolower(trim((string) $order->status)) : '';
        if ($status === '') {
            return ['ok' => false, 'status' => 0, 'body' => null, 'skipped' => 'missing_status'];
        }

        $apiBase = rtrim((string) config('ecomsolvebd.api_base', 'https://api.ecomsolvebd.com'), '/');
        $merchantKey = (string) config('ecomsolvebd.merchant_key', '');
        $secret = (string) config('ecomsolvebd.webhook_secret', '');
        $deploy = trim((string) config('ecomsolvebd.deploy_env', ''));
        if ($apiBase === '' || $merchantKey === '' || $secret === '') {
            return ['ok' => false, 'status' => 0, 'body' => null, 'skipped' => 'missing_config'];
        }

        $payload = [
            'orderNumber' => $orderNumber,
            'status' => $status,
            'source' => 'laravel_admin',
            'updatedAt' => date('c'),
        ];
        $body = json_encode($payload, JSON_UNESCAPED_SLASHES);
        if (! is_string($body)) {
            return ['ok' => false, 'status' => 0, 'body' => null, 'skipped' => 'json_encode'];
        }
        $sig = hash_hmac('sha256', $body, $secret);
        $url = $apiBase.'/api/v1/orders/channels/laravel/status';

        $headers = [
            'Content-Type: application/json',
            'Accept: application/json',
            'x-merchant-key: '.$merchantKey,
            'x-esb-signature: sha256='.$sig,
            'x-webhook-signature: sha256='.$sig,
        ];
        if ($deploy !== '') {
            $headers[] = 'x-fc-deploy-env: '.$deploy;
        }

        $ch = curl_init($url);
        if ($ch === false) {
            return ['ok' => false, 'status' => 0, 'body' => null, 'skipped' => 'curl_init'];
        }
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_HTTPHEADER => $headers,
        ]);
        $resp = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        $decoded = is_string($resp) ? json_decode($resp, true) : null;

        return [
            'ok' => $code >= 200 && $code < 300,
            'status' => $code,
            'body' => $decoded ?? $resp,
        ];
    }
}
