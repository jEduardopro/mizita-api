<?php

declare(strict_types=1);

namespace App\Domains\Subscriptions;

use App\Domains\Subscriptions\Contracts\BusinessDirectory;
use App\Domains\Subscriptions\Contracts\SubscriptionRepository;
use App\Domains\Subscriptions\Infrastructure\Console\CancelSubscriptionCommand;
use App\Domains\Subscriptions\Infrastructure\Console\ExpireSubscriptionsCommand;
use App\Domains\Subscriptions\Infrastructure\Console\ExtendSubscriptionCommand;
use App\Domains\Subscriptions\Infrastructure\Console\GrantSubscriptionCommand;
use App\Domains\Subscriptions\Infrastructure\Eloquent\EloquentSubscriptionRepository;
use App\Domains\Subscriptions\Infrastructure\Gateways\BusinessesBusinessDirectory;
use App\Domains\Subscriptions\Infrastructure\Plans\SubscriptionsBusinessPlan;
use App\Shared\Contracts\BusinessPlan;
use Illuminate\Support\ServiceProvider;

final class SubscriptionsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(SubscriptionRepository::class, EloquentSubscriptionRepository::class);
        $this->app->bind(BusinessDirectory::class, BusinessesBusinessDirectory::class);
        $this->app->bind(BusinessPlan::class, SubscriptionsBusinessPlan::class);
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                GrantSubscriptionCommand::class,
                ExtendSubscriptionCommand::class,
                CancelSubscriptionCommand::class,
                ExpireSubscriptionsCommand::class,
            ]);
        }
    }
}
