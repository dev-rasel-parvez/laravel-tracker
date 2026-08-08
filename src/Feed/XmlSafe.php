<?php

declare(strict_types=1);

namespace EcomSolveBD\LaravelTracker\Feed;

final class XmlSafe
{
    public static function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    public static function stripHtml(string $input): string
    {
        $t = strip_tags($input);
        $t = preg_replace('/\s+/u', ' ', $t) ?? $t;

        return trim($t);
    }

    public static function text(string $input, int $max): string
    {
        $t = self::stripHtml($input);
        if (function_exists('mb_substr')) {
            return mb_substr($t, 0, $max);
        }

        return substr($t, 0, $max);
    }

    public static function isPublicHttpImage(string $url): bool
    {
        $u = trim($url);
        if ($u === '' || ! preg_match('#^https?://#i', $u)) {
            return false;
        }
        if (stripos($u, 'data:') === 0 || stripos($u, 'data:image/') !== false) {
            return false;
        }

        return strlen($u) <= 2000;
    }
}
