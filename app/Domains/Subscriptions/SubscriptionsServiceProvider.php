<?php

declare(strict_types=1);

namespace App\Domains\Subscriptions;

use App\Domains\Subscriptions\Application\UseCases\OpenBillingPortal;
use App\Domains\Subscriptions\Application\UseCases\StartCheckout;
use App\Domains\Subscriptions\Contracts\BillingCheckout;
use App\Domains\Subscriptions\Contracts\BillingContacts;
use App\Domains\Subscriptions\Contracts\BillingCustomers;
use App\Domains\Subscriptions\Contracts\BillingPortal;
use App\Domains\Subscriptions\Contracts\BillingSubscriptions;
use App\Domains\Subscriptions\Contracts\PlanCatalog;
use App\Domains\Subscriptions\Contracts\SubscriptionRepository;
use App\Domains\Subscriptions\Contracts\SubscriptionSyncQueue;
use App\Domains\Subscriptions\Infrastructure\Console\EnforcePaymentGraceCommand;
use App\Domains\Subscriptions\Infrastructure\Console\ReconcileSubscriptionsCommand;
use App\Domains\Subscriptions\Infrastructure\Eloquent\EloquentPlanCatalog;
use App\Domains\Subscriptions\Infrastructure\Eloquent\EloquentSubscriptionRepository;
use App\Domains\Subscriptions\Infrastructure\Gateways\BusinessOwnerBillingContacts;
use App\Domains\Subscriptions\Infrastructure\Http\Controllers\StripeWebhookController;
use App\Domains\Subscriptions\Infrastructure\Plans\SubscriptionsBusinessPlan;
use App\Domains\Subscriptions\Infrastructure\Queue\QueuedSubscriptionSync;
use App\Domains\Subscriptions\Infrastructure\Stripe\StripeApi;
use App\Domains\Subscriptions\Infrastructure\Stripe\StripeBillingCheckout;
use App\Domains\Subscriptions\Infrastructure\Stripe\StripeBillingCustomers;
use App\Domains\Subscriptions\Infrastructure\Stripe\StripeBillingPortal;
use App\Domains\Subscriptions\Infrastructure\Stripe\StripeBillingSubscriptions;
use App\Shared\Contracts\BusinessPlan;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Stripe\ApiRequestor;
use Stripe\HttpClient\CurlClient;

final class SubscriptionsServiceProvider extends ServiceProvider
{
    public const WEBHOOK_RATE_LIMITER = 'stripe-webhook';

    private const API_MIDDLEWARE = ['api', 'auth:sanctum', 'business', 'owner.api'];

    private const WEBHOOK_MIDDLEWARE = ['api', 'throttle:'.self::WEBHOOK_RATE_LIMITER];

    private const WEBHOOK_REQUESTS_PER_MINUTE = 300;

    private const PLAN_PAGE_PATH = '/settings/plan';

    private const CHECKOUT_RETURN_QUERY = '?session_id={CHECKOUT_SESSION_ID}';

    private const STRIPE_TIMEOUT_SECONDS = 10;

    private const STRIPE_CONNECT_TIMEOUT_SECONDS = 5;

    private const STRIPE_MAX_NETWORK_RETRIES = 2;

    public function register(): void
    {
        $this->app->bind(SubscriptionRepository::class, EloquentSubscriptionRepository::class);
        $this->app->bind(PlanCatalog::class, EloquentPlanCatalog::class);
        $this->app->bind(BillingContacts::class, BusinessOwnerBillingContacts::class);
        $this->app->bind(BillingCustomers::class, StripeBillingCustomers::class);
        $this->app->bind(BillingCheckout::class, StripeBillingCheckout::class);
        $this->app->bind(BillingSubscriptions::class, StripeBillingSubscriptions::class);
        $this->app->bind(BillingPortal::class, StripeBillingPortal::class);
        $this->app->bind(SubscriptionSyncQueue::class, QueuedSubscriptionSync::class);
        $this->app->bind(BusinessPlan::class, SubscriptionsBusinessPlan::class);

        $this->registerStripeClient();

        $this->app->when(StartCheckout::class)
            ->needs('$returnUrl')
            ->give(static fn (): string => self::planPageUrl().self::CHECKOUT_RETURN_QUERY);

        $this->app->when(OpenBillingPortal::class)
            ->needs('$returnUrl')
            ->give(static fn (): string => self::planPageUrl());

        $this->app->when(StripeWebhookController::class)
            ->needs('$webhookSecret')
            ->give(static fn (): string => (string) config('services.stripe.webhook_secret'));
    }

    public function boot(): void
    {
        $this->registerWebhookLimiter();

        Route::prefix('api')
            ->middleware(self::API_MIDDLEWARE)
            ->group(__DIR__.'/Infrastructure/Http/routes.php');

        Route::prefix('api')
            ->middleware(self::WEBHOOK_MIDDLEWARE)
            ->group(__DIR__.'/Infrastructure/Http/webhooks.php');

        if ($this->app->runningInConsole()) {
            $this->commands([
                EnforcePaymentGraceCommand::class,
                ReconcileSubscriptionsCommand::class,
            ]);
        }
    }

    private function registerStripeClient(): void
    {
        $this->app->singleton(StripeApi::class, static function (): StripeApi {
            $httpClient = new CurlClient;
            $httpClient->setTimeout(self::STRIPE_TIMEOUT_SECONDS);
            $httpClient->setConnectTimeout(self::STRIPE_CONNECT_TIMEOUT_SECONDS);
            ApiRequestor::setHttpClient($httpClient);

            return new StripeApi(
                secretKey: (string) config('services.stripe.secret'),
                maxNetworkRetries: self::STRIPE_MAX_NETWORK_RETRIES,
            );
        });
    }

    private function registerWebhookLimiter(): void
    {
        RateLimiter::for(
            self::WEBHOOK_RATE_LIMITER,
            static fn (Request $request): Limit => Limit::perMinute(self::WEBHOOK_REQUESTS_PER_MINUTE)
                ->by('stripe-webhook:'.(string) $request->ip()),
        );
    }

    private static function planPageUrl(): string
    {
        return rtrim((string) config('app.url'), '/').self::PLAN_PAGE_PATH;
    }
}
