<?php

declare(strict_types=1);

namespace App\Domains\Statistics;

use App\Domains\Statistics\Contracts\BusinessCurrency;
use App\Domains\Statistics\Contracts\BusinessTimezone;
use App\Domains\Statistics\Contracts\StatisticsReader;
use App\Domains\Statistics\Infrastructure\Gateways\BusinessesBusinessCurrency;
use App\Domains\Statistics\Infrastructure\Gateways\BusinessesBusinessTimezone;
use App\Domains\Statistics\Infrastructure\Gateways\EloquentStatisticsReader;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

final class StatisticsServiceProvider extends ServiceProvider
{
    private const API_MIDDLEWARE = ['api', 'auth:sanctum', 'business', 'owner.api'];

    public function register(): void
    {
        $this->app->bind(StatisticsReader::class, EloquentStatisticsReader::class);
        $this->app->bind(BusinessTimezone::class, BusinessesBusinessTimezone::class);
        $this->app->bind(BusinessCurrency::class, BusinessesBusinessCurrency::class);
    }

    public function boot(): void
    {
        Route::prefix('api')
            ->middleware(self::API_MIDDLEWARE)
            ->group(__DIR__.'/Infrastructure/Http/routes.php');
    }
}
