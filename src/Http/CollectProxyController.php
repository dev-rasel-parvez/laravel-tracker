<?php

declare(strict_types=1);

namespace EcomSolveBD\LaravelTracker\Http;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;

/**
 * Same-origin collect proxy (Woo esb/v1/ingest parity).
 * POST /ecomsolvebd/collect
 */
final class CollectProxyController extends Controller
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
            return response('{"error":"missing_config"}', 400)->header('Content-Type', 'application/json');
        }

        $url = $apiBase.'/api/v1/tracking/collect?'.http_build_query(['key' => $merchantKey]);
        $body = $request->getContent();
        $deploy = trim((string) config('ecomsolvebd.deploy_env', ''));

        $headers = [
            'Content-Type: application/json',
            'Accept: application/json',
            'x-merchant-key: '.$merchantKey,
        ];
        if ($deploy !== '') {
            $headers[] = 'x-fc-deploy-env: '.$deploy;
        }
        $ua = trim((string) $request->userAgent());
        if ($ua !== '') {
            $headers[] = 'X-Client-User-Agent: '.$ua;
        }
        $xff = trim((string) $request->header('x-forwarded-for', $request->ip() ?? ''));
        if ($xff !== '') {
            $headers[] = 'X-Forwarded-For: '.$xff;
        }

        $ch = curl_init($url);
        if ($ch === false) {
            return response('{"error":"bad_gateway"}', 502)->header('Content-Type', 'application/json');
        }
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTPHEADER => $headers,
        ]);
        $respBody = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);

        if ($respBody === false) {
            return response(
                json_encode(['error' => ['code' => 'BAD_GATEWAY', 'message' => $err ?: 'upstream_failed']]),
                502
            )->header('Content-Type', 'application/json');
        }

        return response(is_string($respBody) ? $respBody : '', $code > 0 ? $code : 502)
            ->header('Content-Type', 'application/json')
            ->header('Cache-Control', 'no-store');
    }
}
