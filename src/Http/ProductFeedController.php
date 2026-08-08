<?php

declare(strict_types=1);

namespace EcomSolveBD\LaravelTracker\Http;

use EcomSolveBD\LaravelTracker\Feed\EloquentProductFeedProvider;
use EcomSolveBD\LaravelTracker\Feed\ProductFeedProvider;
use EcomSolveBD\LaravelTracker\Feed\ProductFeedXmlBuilder;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;

/**
 * Public product feeds (Woo /feed/*.xml parity).
 * GET /feed/{products|facebook|tiktok|google}.xml
 */
final class ProductFeedController extends Controller
{
    public function __invoke(Request $request, string $channel): Response
    {
        $channel = strtolower($channel);
        $allowed = ['products', 'facebook', 'tiktok', 'google'];
        if (! in_array($channel, $allowed, true)) {
            abort(404);
        }

        if (! (bool) config('ecomsolvebd.feeds.enabled', true)) {
            return response('Product feeds disabled.', 404)
                ->header('Content-Type', 'text/plain; charset=utf-8');
        }

        $provider = $this->provider();
        $currency = (string) config('ecomsolvebd.feeds.currency', 'BDT');
        $storeName = trim((string) config('ecomsolvebd.feeds.store_name', ''));
        if ($storeName === '') {
            $storeName = trim((string) config('app.name', 'Store'));
        }
        $origin = rtrim((string) config('app.url', ''), '/') ?: $request->getSchemeAndHttpHost();
        $self = rtrim($origin, '/') . '/feed/' . $channel . '.xml';

        $xml = match ($channel) {
            'products' => ProductFeedXmlBuilder::productsCatalog($provider->catalogProducts(), $currency),
            'facebook' => ProductFeedXmlBuilder::facebookRss($provider->channelItems(), $storeName, $origin, $self),
            'tiktok' => ProductFeedXmlBuilder::tiktok($provider->channelItems(), $currency),
            default => ProductFeedXmlBuilder::googleRss($provider->channelItems(), $storeName, $origin),
        };

        return response($xml, 200)
            ->header('Content-Type', 'application/xml; charset=utf-8')
            ->header('Cache-Control', 'public, max-age=300');
    }

    private function provider(): ProductFeedProvider
    {
        $class = (string) config('ecomsolvebd.feeds.provider', '');
        if ($class !== '' && class_exists($class) && is_subclass_of($class, ProductFeedProvider::class)) {
            return app($class);
        }

        return app(EloquentProductFeedProvider::class);
    }
}
