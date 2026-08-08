<?php

declare(strict_types=1);

namespace EcomSolveBD\LaravelTracker\Http;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;

/**
 * Same-origin attribution-config proxy (Woo esb/v1/attribution-config parity).
 * GET /ecomsolvebd/attribution-config
 */
final class AttributionConfigProxyController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $apiBase = rtrim((string) config('ecomsolvebd.api_base', 'https://api.ecomsolvebd.com'), '/');
        $merchantKey = trim((string) (
            $request->query('key')
            ?: $request->header('x-merchant-key')
            ?: config('ecomsolvebd.merchant_key', '')
        ));
        if ($apiBase === '' || $merchantKey === '') {
            return response('{}', 400)->header('Content-Type', 'application/json');
        }

        $q = [
            'key' => $merchantKey,
            'store' => 'laravel',
        ];
        $url = $apiBase.'/api/v1/tracking/attribution-config?'.http_build_query($q);
        $deploy = trim((string) config('ecomsolvebd.deploy_env', ''));

        $headers = [
            'Accept: application/json',
            'x-merchant-key: '.$merchantKey,
        ];
        if ($deploy !== '') {
            $headers[] = 'x-fc-deploy-env: '.$deploy;
        }

        $ch = curl_init($url);
        if ($ch === false) {
            return response('{}', 502)->header('Content-Type', 'application/json');
        }
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 25,
            CURLOPT_HTTPHEADER => $headers,
        ]);
        $respBody = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return response(is_string($respBody) ? $respBody : '{}', $code > 0 ? $code : 502)
            ->header('Content-Type', 'application/json')
            ->header('Cache-Control', 'no-store');
    }
}
