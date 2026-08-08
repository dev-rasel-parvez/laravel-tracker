<?php

declare(strict_types=1);

namespace EcomSolveBD\LaravelTracker;

use EcomSolveBD\LaravelTracker\Feed\EloquentProductFeedProvider;
use EcomSolveBD\LaravelTracker\Http\AttributionConfigProxyController;
use EcomSolveBD\LaravelTracker\Http\CollectProxyController;
use EcomSolveBD\LaravelTracker\Http\OrderStatusInboundController;
use EcomSolveBD\LaravelTracker\Http\ProductFeedController;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

final class EcomSolveBdServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/ecomsolvebd.php', 'ecomsolvebd');

        $this->app->singleton(OrderWebhookPoster::class, function ($app) {
            return new OrderWebhookPoster(
                (string) config('ecomsolvebd.api_base'),
                (string) config('ecomsolvebd.merchant_key'),
                (string) config('ecomsolvebd.webhook_secret'),
                (string) config('ecomsolvebd.deploy_env'),
            );
        });

        $this->app->singleton(CollectClient::class, function ($app) {
            return new CollectClient(
                (string) config('ecomsolvebd.api_base'),
                (string) config('ecomsolvebd.merchant_key'),
                (string) config('ecomsolvebd.deploy_env'),
            );
        });

        $this->app->singleton(EloquentProductFeedProvider::class);
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__ . '/../config/ecomsolvebd.php' => config_path('ecomsolvebd.php'),
        ], 'ecomsolvebd-config');

        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'ecomsolvebd');

        Blade::directive('ecomsolvebdTracker', static function () {
            return "<?php echo view('ecomsolvebd::tracker')->render(); ?>";
        });

        Blade::directive('ecomsolvebdGtag', static function () {
            return "<?php echo view('ecomsolvebd::gtag')->render(); ?>";
        });

        // No CSRF — HMAC verified inside controller (SaaS → storefront status push).
        $orderStatus = Route::post('/ecomsolvebd/order-status', OrderStatusInboundController::class)
            ->name('ecomsolvebd.order-status');
        if (method_exists($orderStatus, 'withoutMiddleware')) {
            $orderStatus->withoutMiddleware([VerifyCsrfToken::class]);
        }

        // First-party store proxy (Woo REST ingest parity) — browser → Laravel → SaaS.
        $collectProxy = Route::post('/ecomsolvebd/collect', CollectProxyController::class)
            ->name('ecomsolvebd.collect');
        if (method_exists($collectProxy, 'withoutMiddleware')) {
            $collectProxy->withoutMiddleware([VerifyCsrfToken::class]);
        }
        Route::get('/ecomsolvebd/attribution-config', AttributionConfigProxyController::class)
            ->name('ecomsolvebd.attribution-config');

        // Public product feeds (Woo /feed/*.xml parity) — no auth; Cache-Control max-age=300.
        Route::get('/feed/{channel}.xml', ProductFeedController::class)
            ->where('channel', 'products|facebook|tiktok|google')
            ->name('ecomsolvebd.product-feed');

        $events = config('ecomsolvebd.order_created_events', []);
        if (is_array($events)) {
            foreach ($events as $eventClass) {
                if (!is_string($eventClass) || $eventClass === '' || !class_exists($eventClass)) {
                    continue;
                }
                Event::listen($eventClass, function (object $event) {
                    /** @var OrderWebhookPoster $poster */
                    $poster = app(OrderWebhookPoster::class);
                    $payload = OrderPayloadFactory::fromEvent($event);
                    if ($payload !== null) {
                        $poster->postOrder($payload);
                    }
                });
            }
        }

        // Admin → SaaS status (optional; loop-safe when status_changed_by=EcomSolveBD).
        if (filter_var(config('ecomsolvebd.status_outbound.enabled', true), FILTER_VALIDATE_BOOLEAN)) {
            $orderModel = (string) config('ecomsolvebd.order_model', 'App\\Models\\Order');
            if ($orderModel !== '' && class_exists($orderModel) && is_subclass_of($orderModel, Model::class)) {
                $orderModel::observe(OrderStatusOutboundObserver::class);
            }
        }
    }
}
