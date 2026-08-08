<?php

declare(strict_types=1);

namespace EcomSolveBD\LaravelTracker;

/**
 * First-party ingest ladder (Woo / Shopify / ESB parity):
 * verified CNAME → custom_subdomain
 * auto → same-origin store proxy
 * else → direct_api
 */
final class FirstParty
{
    public const CNAME_TARGET = 'collect.ecomsolvebd.com';

    public const PROXY_COLLECT_PATH = '/ecomsolvebd/collect';

    public const PROXY_ATTR_PATH = '/ecomsolvebd/attribution-config';

    /**
     * @return array{
     *   endpoint: string,
     *   attributionConfigUrl: string,
     *   ingestMode: string,
     *   credentials: string,
     *   trackingHostname: string,
     *   firstPartyMode: string,
     *   trackingHostnameVerified: bool
     * }
     */
    public static function browserEndpoints(?array $settings = null): array
    {
        $s = $settings ?? self::resolvedSettings();
        $mode = ($s['first_party_mode'] ?? 'auto') === 'custom' ? 'custom' : 'auto';
        $hostname = self::normalizeHostname((string) ($s['tracking_hostname'] ?? ''));
        $verified = ! empty($s['tracking_hostname_verified']) && $hostname !== '';
        $merchantKey = (string) ($s['merchant_key'] ?? config('ecomsolvebd.merchant_key', ''));
        $apiBase = rtrim((string) ($s['api_base'] ?? config('ecomsolvebd.api_base', 'https://api.ecomsolvebd.com')), '/');

        $directCollect = $apiBase.'/api/v1/tracking/collect';
        $directAttr = $apiBase.'/api/v1/tracking/attribution-config?'.http_build_query([
            'key' => $merchantKey,
            'store' => 'laravel',
        ]);

        if (($mode === 'custom' || $mode === 'auto') && $verified && $hostname !== '') {
            $hostBase = 'https://'.$hostname;
            $q = http_build_query(['key' => $merchantKey]);

            return [
                'endpoint' => $hostBase.'/api/v1/tracking/collect?'.$q,
                'attributionConfigUrl' => $hostBase.'/api/v1/tracking/attribution-config?'.http_build_query([
                    'key' => $merchantKey,
                    'store' => 'laravel',
                ]),
                'ingestMode' => 'custom_subdomain',
                'credentials' => 'omit',
                'trackingHostname' => $hostname,
                'firstPartyMode' => $mode,
                'trackingHostnameVerified' => true,
            ];
        }

        if ($mode === 'auto' && $merchantKey !== '') {
            $origin = self::storeOrigin();

            return [
                'endpoint' => $origin.self::PROXY_COLLECT_PATH.($merchantKey !== '' ? '?'.http_build_query(['key' => $merchantKey]) : ''),
                'attributionConfigUrl' => $origin.self::PROXY_ATTR_PATH.'?'.http_build_query([
                    'key' => $merchantKey,
                    'store' => 'laravel',
                ]),
                'ingestMode' => 'store_proxy',
                'credentials' => 'omit',
                'trackingHostname' => '',
                'firstPartyMode' => $mode,
                'trackingHostnameVerified' => false,
            ];
        }

        return [
            'endpoint' => $directCollect,
            'attributionConfigUrl' => $directAttr,
            'ingestMode' => 'direct_api',
            'credentials' => 'omit',
            'trackingHostname' => '',
            'firstPartyMode' => $mode,
            'trackingHostnameVerified' => false,
        ];
    }

    /**
     * Local config merged with optional SaaS sync (dashboard DNS verify).
     *
     * @return array<string, mixed>
     */
    public static function resolvedSettings(): array
    {
        $local = [
            'api_base' => (string) config('ecomsolvebd.api_base', 'https://api.ecomsolvebd.com'),
            'merchant_key' => (string) config('ecomsolvebd.merchant_key', ''),
            'first_party_mode' => strtolower((string) config('ecomsolvebd.first_party.mode', 'auto')) === 'custom'
                ? 'custom'
                : 'auto',
            'tracking_subdomain_label' => self::sanitizeSubdomainLabel(
                (string) config('ecomsolvebd.first_party.subdomain_label', 'tracking')
            ),
            'tracking_hostname' => self::normalizeHostname(
                (string) config('ecomsolvebd.first_party.hostname', '')
            ),
            'tracking_hostname_verified' => filter_var(
                config('ecomsolvebd.first_party.hostname_verified', false),
                FILTER_VALIDATE_BOOLEAN
            ),
        ];

        if (! filter_var(config('ecomsolvebd.first_party.sync_from_saas', true), FILTER_VALIDATE_BOOLEAN)) {
            return $local;
        }

        $remote = self::fetchSaasSettings($local['api_base'], $local['merchant_key']);
        if ($remote === null) {
            return $local;
        }

        return array_merge($local, array_filter([
            'first_party_mode' => isset($remote['firstPartyMode'])
                ? ((string) $remote['firstPartyMode'] === 'custom' ? 'custom' : 'auto')
                : null,
            'tracking_subdomain_label' => isset($remote['trackingSubdomainLabel'])
                ? self::sanitizeSubdomainLabel((string) $remote['trackingSubdomainLabel'])
                : null,
            'tracking_hostname' => isset($remote['trackingHostname'])
                ? self::normalizeHostname((string) $remote['trackingHostname'])
                : null,
            'tracking_hostname_verified' => array_key_exists('trackingHostnameVerified', $remote)
                ? (bool) $remote['trackingHostnameVerified']
                : null,
        ], static fn ($v) => $v !== null));
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function fetchSaasSettings(string $apiBase, string $merchantKey): ?array
    {
        if ($apiBase === '' || $merchantKey === '') {
            return null;
        }

        $cacheKey = 'ecomsolvebd.first_party.'.sha1($merchantKey);
        $ttl = (int) config('ecomsolvebd.first_party.sync_ttl_seconds', 60);

        try {
            if (function_exists('cache') && $ttl > 0) {
                $cached = cache()->get($cacheKey);
                if (is_array($cached)) {
                    return $cached;
                }
            }
        } catch (\Throwable) {
            // cache optional
        }

        $url = rtrim($apiBase, '/').'/api/v1/laravel/tracker-config';
        $deploy = trim((string) config('ecomsolvebd.deploy_env', ''));

        try {
            $ch = curl_init($url);
            if ($ch === false) {
                return null;
            }
            $headers = [
                'Accept: application/json',
                'x-merchant-key: '.$merchantKey,
            ];
            if ($deploy !== '') {
                $headers[] = 'x-fc-deploy-env: '.$deploy;
            }
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 3,
                CURLOPT_HTTPHEADER => $headers,
            ]);
            $body = curl_exec($ch);
            $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            if ($code < 200 || $code >= 300 || ! is_string($body) || $body === '') {
                return null;
            }
            /** @var array<string, mixed>|null $json */
            $json = json_decode($body, true);
            if (! is_array($json) || empty($json['ok']) || ! is_array($json['data'] ?? null)) {
                return null;
            }
            /** @var array<string, mixed> $data */
            $data = $json['data'];
            try {
                if (function_exists('cache') && $ttl > 0) {
                    cache()->put($cacheKey, $data, $ttl);
                }
            } catch (\Throwable) {
                // ignore
            }

            return $data;
        } catch (\Throwable) {
            return null;
        }
    }

    public static function storeOrigin(): string
    {
        try {
            if (function_exists('url')) {
                return rtrim((string) url('/'), '/');
            }
        } catch (\Throwable) {
            // fall through
        }

        $appUrl = rtrim((string) config('app.url', ''), '/');

        return $appUrl !== '' ? $appUrl : '';
    }

    public static function normalizeHostname(string $raw): string
    {
        $t = strtolower(trim($raw));
        if ($t === '') {
            return '';
        }
        $t = (string) preg_replace('#^https?://#', '', $t);
        $t = (string) preg_replace('#/.*$#', '', $t);
        $t = rtrim($t, '.');
        if (! preg_match('/^[a-z0-9]([a-z0-9.-]*[a-z0-9])?$/i', $t)) {
            return '';
        }

        return substr($t, 0, 253);
    }

    public static function sanitizeSubdomainLabel(string $raw): string
    {
        $t = strtolower(trim($raw));
        $t = (string) preg_replace('#^https?://#', '', $t);
        $t = (string) preg_replace('#/.*$#', '', $t);
        if (str_contains($t, '.')) {
            $parts = explode('.', $t);
            $t = (string) ($parts[0] ?? '');
        }
        $t = (string) preg_replace('/[^a-z0-9-]/', '', $t);
        $t = trim($t, '-');
        if (strlen($t) > 32) {
            $t = substr($t, 0, 32);
        }

        return $t !== '' ? $t : 'tracking';
    }

    public static function storeBaseDomain(): string
    {
        $host = parse_url(self::storeOrigin().'/', PHP_URL_HOST);
        $host = is_string($host) ? strtolower(trim($host)) : '';
        if (str_starts_with($host, 'www.')) {
            $host = substr($host, 4);
        }

        return $host;
    }

    public static function previewTrackingHostname(string $label = ''): string
    {
        $label = self::sanitizeSubdomainLabel($label !== '' ? $label : (string) config('ecomsolvebd.first_party.subdomain_label', 'tracking'));
        $base = self::storeBaseDomain();
        if ($label === '' || $base === '') {
            return '';
        }

        return self::normalizeHostname($label.'.'.$base);
    }
}
