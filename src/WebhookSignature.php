<?php

declare(strict_types=1);

namespace EcomSolveBD\LaravelTracker;

/**
 * Verifies HMAC-SHA256 signatures from EcomSolveBD outbound webhooks.
 */
final class WebhookSignature
{
    public static function isValid(string $rawBody, string $secret, ?string $signatureHeader): bool
    {
        $provided = trim((string) $signatureHeader);
        $provided = (string) preg_replace('/^sha256=/i', '', $provided);
        if ($provided === '' || $secret === '') {
            return false;
        }
        $expected = hash_hmac('sha256', $rawBody, $secret);
        return hash_equals($expected, $provided);
    }
}
