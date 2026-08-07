<?php

declare(strict_types=1);

namespace EcomSolveBD\LaravelTracker;

/**
 * Thin browser-event collect client (server-side fallback / SPA backends).
 */
final class CollectClient
{
    public function __construct(
        private readonly string $apiBase,
        private readonly string $merchantKey,
        private readonly string $deployEnv = '',
    ) {
    }

    /**
     * @param array<string, mixed> $body Collect payload (event, user_id, …)
     * @return array{status:int,body:mixed}
     */
    public function collect(array $body): array
    {
        if ($this->merchantKey === '') {
            throw new \RuntimeException('ECOMSOLVEBD_MERCHANT_KEY is required');
        }

        $url = rtrim($this->apiBase, '/') . '/api/v1/tracking/collect';
        $json = json_encode($body, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        $headers = [
            'Content-Type: application/json',
            'x-merchant-key: ' . $this->merchantKey,
        ];
        if ($this->deployEnv !== '') {
            $headers[] = 'x-fc-deploy-env: ' . $this->deployEnv;
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_POSTFIELDS => $json,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 15,
        ]);
        $raw = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);
        if ($raw === false) {
            throw new \RuntimeException('collect_failed: ' . $err);
        }

        return ['status' => $status, 'body' => json_decode($raw, true)];
    }
}
