<?php

declare(strict_types=1);

namespace EcomSolveBD\LaravelTracker;

/**
 * Signs and POSTs order JSON to EcomSolveBD Laravel ingest.
 */
final class OrderWebhookPoster
{
    public function __construct(
        private readonly string $apiBase,
        private readonly string $merchantKey,
        private readonly string $webhookSecret,
        private readonly string $deployEnv = '',
    ) {
    }

    /**
     * @param array<string, mixed> $payload Website-order ingest shape
     * @return array{status:int,body:mixed}
     */
    public function postOrder(array $payload): array
    {
        if ($this->merchantKey === '' || $this->webhookSecret === '') {
            throw new \RuntimeException('ECOMSOLVEBD_MERCHANT_KEY and ECOMSOLVEBD_WEBHOOK_SECRET are required');
        }

        $url = rtrim($this->apiBase, '/') . '/api/v1/orders/channels/laravel';
        $body = json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        $sig = hash_hmac('sha256', $body, $this->webhookSecret);

        $headers = [
            'Content-Type: application/json',
            'x-merchant-key: ' . $this->merchantKey,
            'x-esb-signature: sha256=' . $sig,
            'x-webhook-signature: sha256=' . $sig,
        ];
        if ($this->deployEnv !== '') {
            $headers[] = 'x-fc-deploy-env: ' . $this->deployEnv;
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 20,
        ]);
        $raw = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);
        if ($raw === false) {
            throw new \RuntimeException('laravel_order_webhook_failed: ' . $err);
        }

        return [
            'status' => $status,
            'body' => json_decode($raw, true),
        ];
    }
}
