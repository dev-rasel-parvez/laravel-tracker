<?php

declare(strict_types=1);

namespace EcomSolveBD\LaravelTracker;

/**
 * Checkout Shield — server-side check before saving an order (fraud check + SMS OTP + repeat-order timer).
 *
 *   $r = app(CheckoutShield::class)->verify($request->input('phone'));
 *   if (! $r['allow']) { return back()->withErrors(['phone' => $r['message']])->withInput(); }
 *
 * or as a validation rule:  'phone' => ['required', new \EcomSolveBD\LaravelTracker\Rules\CheckoutShieldPhone()]
 *
 * Fail-open: timeout / network / unexpected answer → allow. Only the store's own rules (SMS code needed,
 * repeat-order timer) return allow=false. Inert unless EcomSolveBD turned Checkout Shield on for the store.
 */
final class CheckoutShield
{
    /** @var array<string, array{allow:bool,message:string,reason:string}> */
    private array $cache = [];

    public function __construct(
        private readonly string $apiBase,
        private readonly string $merchantKey,
        private readonly string $deployEnv = '',
        private readonly int $timeoutSeconds = 8,
    ) {
    }

    /**
     * @return array{allow:bool,message:string,reason:string}
     */
    public function verify(?string $phone, ?string $host = null, ?string $customerIp = null): array
    {
        $allow = ['allow' => true, 'message' => '', 'reason' => 'ok'];
        $digits = preg_replace('/\D+/', '', (string) $phone) ?? '';
        if ($this->merchantKey === '' || strlen($digits) < 10) {
            return $allow;
        }
        if (isset($this->cache[$digits])) {
            return $this->cache[$digits];
        }

        $host = $host ?? (function_exists('request') ? (string) request()->getHost() : '');
        $customerIp = $customerIp ?? (function_exists('request') ? (string) request()->ip() : '');
        $url = rtrim($this->apiBase, '/') . '/api/v1/checkout-shield/public/verify-order';
        $headers = ['Content-Type: application/json', 'Accept: application/json', 'x-merchant-key: ' . $this->merchantKey];
        if ($this->deployEnv !== '') {
            $headers[] = 'x-fc-deploy-env: ' . $this->deployEnv;
        }

        $out = $allow;
        try {
            $json = json_encode([
                'merchant_key' => $this->merchantKey,
                'host' => $host,
                'phone' => (string) $phone,
                'customer_ip' => $customerIp,
            ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_HTTPHEADER => $headers,
                CURLOPT_POSTFIELDS => $json,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CONNECTTIMEOUT => 3,
                CURLOPT_TIMEOUT => $this->timeoutSeconds,
            ]);
            $raw = curl_exec($ch);
            $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            if (is_string($raw) && $status === 200) {
                $body = json_decode($raw, true);
                $data = is_array($body) && isset($body['data']) && is_array($body['data']) ? $body['data'] : null;
                // Only an explicit `allow: false` stops the order.
                if (is_array($data) && array_key_exists('allow', $data) && $data['allow'] === false) {
                    $out = [
                        'allow' => false,
                        'message' => is_string($data['message'] ?? null) && $data['message'] !== ''
                            ? $data['message']
                            : 'অর্ডার নিশ্চিত করতে মোবাইলে পাঠানো কোড দিন।',
                        'reason' => is_string($data['reason'] ?? null) ? $data['reason'] : 'otp_required',
                    ];
                }
            }
        } catch (\Throwable) {
            $out = $allow;
        }

        return $this->cache[$digits] = $out;
    }
}
