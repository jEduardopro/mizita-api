<?php

declare(strict_types=1);

namespace App\Domains\PublicCatalog;

use App\Domains\PublicCatalog\Contracts\GuestBookingDesk;
use App\Domains\PublicCatalog\Contracts\GuestBookings;
use App\Domains\PublicCatalog\Contracts\PublishedBookingHorizon;
use App\Domains\PublicCatalog\Contracts\PublishedBookingPolicy;
use App\Domains\PublicCatalog\Contracts\PublishedBrand;
use App\Domains\PublicCatalog\Contracts\PublishedBusinesses;
use App\Domains\PublicCatalog\Contracts\PublishedContact;
use App\Domains\PublicCatalog\Contracts\PublishedLocation;
use App\Domains\PublicCatalog\Contracts\PublishedOpenState;
use App\Domains\PublicCatalog\Contracts\PublishedSchedule;
use App\Domains\PublicCatalog\Contracts\PublishedServices;
use App\Domains\PublicCatalog\Contracts\PublishedSlots;
use App\Domains\PublicCatalog\Contracts\PublishedTeam;
use App\Domains\PublicCatalog\Infrastructure\Gateways\AddressesPublishedLocation;
use App\Domains\PublicCatalog\Infrastructure\Gateways\AppointmentsGuestBookingDesk;
use App\Domains\PublicCatalog\Infrastructure\Gateways\AppointmentsGuestBookings;
use App\Domains\PublicCatalog\Infrastructure\Gateways\AvailabilityPublishedBookingHorizon;
use App\Domains\PublicCatalog\Infrastructure\Gateways\AvailabilityPublishedOpenState;
use App\Domains\PublicCatalog\Infrastructure\Gateways\AvailabilityPublishedSchedule;
use App\Domains\PublicCatalog\Infrastructure\Gateways\AvailabilityPublishedSlots;
use App\Domains\PublicCatalog\Infrastructure\Gateways\BookingPagesPublishedBrand;
use App\Domains\PublicCatalog\Infrastructure\Gateways\BookingPoliciesPublishedBookingPolicy;
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

    public const BOOKING_FLOW_SEGMENT = 'book';

    public const SERVICE_PAGE_SLUG_PATTERN = '(?!'.self::BOOKING_FLOW_SEGMENT.'$)'.self::SLUG_PATTERN;

    public const REFERENCE_CODE_PATTERN = '[A-Za-z0-9]{8}';

    private const PUBLIC_CATALOG_LIMITER = 'public-catalog';

    private const GUEST_BOOKING_LIMITER = 'public-booking';

    private const REQUESTS_PER_MINUTE = 60;

    private const REQUESTS_PER_HOUR = 600;

    private const BOOKINGS_PER_MINUTE = 5;

    private const BOOKINGS_PER_HOUR = 20;

    public function register(): void
    {
        $this->app->bind(PublishedBusinesses::class, BusinessesPublishedBusinesses::class);
        $this->app->bind(PublishedServices::class, ServicesPublishedServices::class);
        $this->app->bind(PublishedTeam::class, StaffPublishedTeam::class);
        $this->app->bind(PublishedSchedule::class, AvailabilityPublishedSchedule::class);
        $this->app->bind(PublishedOpenState::class, AvailabilityPublishedOpenState::class);
        $this->app->bind(PublishedBookingHorizon::class, AvailabilityPublishedBookingHorizon::class);
        $this->app->bind(PublishedSlots::class, AvailabilityPublishedSlots::class);
        $this->app->bind(PublishedBrand::class, BookingPagesPublishedBrand::class);
        $this->app->bind(PublishedBookingPolicy::class, BookingPoliciesPublishedBookingPolicy::class);
        $this->app->bind(PublishedLocation::class, AddressesPublishedLocation::class);
        $this->app->bind(PublishedContact::class, PhonesLinksPublishedContact::class);
        $this->app->bind(GuestBookings::class, AppointmentsGuestBookings::class);
        $this->app->bind(GuestBookingDesk::class, AppointmentsGuestBookingDesk::class);
    }

    public function boot(): void
    {
        $this->registerVisitorLimiter();
        $this->registerGuestBookingLimiter();

        Route::prefix('api')
            ->middleware(['api', 'throttle:'.self::PUBLIC_CATALOG_LIMITER])
            ->group(__DIR__.'/Infrastructure/Http/routes.php');

        Route::prefix('api')
            ->middleware(['api', 'throttle:'.self::GUEST_BOOKING_LIMITER])
            ->group(__DIR__.'/Infrastructure/Http/booking-routes.php');
    }

    private function registerVisitorLimiter(): void
    {
        RateLimiter::for(self::PUBLIC_CATALOG_LIMITER, static fn (Request $request): array => [
            Limit::perMinute(self::REQUESTS_PER_MINUTE)->by('minute:'.self::limiterKeyFor($request)),
            Limit::perHour(self::REQUESTS_PER_HOUR)->by('hour:'.self::limiterKeyFor($request)),
        ]);
    }

    private function registerGuestBookingLimiter(): void
    {
        RateLimiter::for(self::GUEST_BOOKING_LIMITER, static fn (Request $request): array => [
            Limit::perMinute(self::BOOKINGS_PER_MINUTE)->by('booking-minute:'.self::limiterKeyFor($request)),
            Limit::perHour(self::BOOKINGS_PER_HOUR)->by('booking-hour:'.self::limiterKeyFor($request)),
        ]);
    }

    private static function limiterKeyFor(Request $request): string
    {
        return 'ip:'.(string) $request->ip();
    }
}
