<?php

declare(strict_types=1);

namespace EcomSolveBD\LaravelTracker;

use EcomSolveBD\LaravelTracker\Http\OrderStatusInboundController;
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
        Route::post('/ecomsolvebd/order-status', OrderStatusInboundController::class)
            ->name('ecomsolvebd.order-status');

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
    }
}
