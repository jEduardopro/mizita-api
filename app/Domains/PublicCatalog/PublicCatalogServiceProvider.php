<?php

declare(strict_types=1);

namespace App\Domains\PublicCatalog;

use App\Domains\PublicCatalog\Contracts\PublishedBrand;
use App\Domains\PublicCatalog\Contracts\PublishedBusinesses;
use App\Domains\PublicCatalog\Contracts\PublishedContact;
use App\Domains\PublicCatalog\Contracts\PublishedLocation;
use App\Domains\PublicCatalog\Contracts\PublishedSchedule;
use App\Domains\PublicCatalog\Contracts\PublishedServices;
use App\Domains\PublicCatalog\Contracts\PublishedTeam;
use App\Domains\PublicCatalog\Infrastructure\Gateways\AddressesPublishedLocation;
use App\Domains\PublicCatalog\Infrastructure\Gateways\AvailabilityPublishedSchedule;
use App\Domains\PublicCatalog\Infrastructure\Gateways\BookingPagesPublishedBrand;
use App\Domains\PublicCatalog\Infrastructure\Gateways\BusinessesPublishedBusinesses;
use App\Domains\PublicCatalog\Infrastructure\Gateways\PhonesLinksPublishedContact;
use App\Domains\PublicCatalog\Infrastructure\Gateways\ServicesPublishedServices;
use App\Domains\PublicCatalog\Infrastructure\Gateways\StaffPublishedTeam;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

final class PublicCatalogServiceProvider extends ServiceProvider
{
    public const SLUG_PATTERN = '[a-z0-9]+(?:-[a-z0-9]+)*';

    // Laravel registers /storage/{path} after routes/web.php, so the root catch-all
    // would swallow it without this exclusion.
    public const BOOKING_PAGE_SLUG_PATTERN = '(?!storage$)'.self::SLUG_PATTERN;

    private const PUBLIC_CATALOG_LIMITER = 'public-catalog';

    private const REQUESTS_PER_MINUTE = 60;

    private const REQUESTS_PER_HOUR = 600;

    public function register(): void
    {
        $this->app->bind(PublishedBusinesses::class, BusinessesPublishedBusinesses::class);
        $this->app->bind(PublishedServices::class, ServicesPublishedServices::class);
        $this->app->bind(PublishedTeam::class, StaffPublishedTeam::class);
        $this->app->bind(PublishedSchedule::class, AvailabilityPublishedSchedule::class);
        $this->app->bind(PublishedBrand::class, BookingPagesPublishedBrand::class);
        $this->app->bind(PublishedLocation::class, AddressesPublishedLocation::class);
        $this->app->bind(PublishedContact::class, PhonesLinksPublishedContact::class);
    }

    public function boot(): void
    {
        $this->registerVisitorLimiter();

        Route::prefix('api')
            ->middleware(['api', 'throttle:'.self::PUBLIC_CATALOG_LIMITER])
            ->group(__DIR__.'/Infrastructure/Http/routes.php');
    }

    private function registerVisitorLimiter(): void
    {
        RateLimiter::for(self::PUBLIC_CATALOG_LIMITER, static fn (Request $request): array => [
            Limit::perMinute(self::REQUESTS_PER_MINUTE)->by('minute:'.self::limiterKeyFor($request)),
            Limit::perHour(self::REQUESTS_PER_HOUR)->by('hour:'.self::limiterKeyFor($request)),
        ]);
    }

    private static function limiterKeyFor(Request $request): string
    {
        return 'ip:'.(string) $request->ip();
    }
}
