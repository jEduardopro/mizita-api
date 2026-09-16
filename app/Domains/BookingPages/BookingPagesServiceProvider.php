<?php

declare(strict_types=1);

namespace App\Domains\BookingPages;

use App\Domains\BookingPages\Contracts\BookingPageImages;
use App\Domains\BookingPages\Contracts\BookingPageRepository;
use App\Domains\BookingPages\Contracts\CurrentBookingPage;
use App\Domains\BookingPages\Infrastructure\Eloquent\EloquentBookingPageRepository;
use App\Domains\BookingPages\Infrastructure\Eloquent\Models\BookingPageModel;
use App\Domains\BookingPages\Infrastructure\Media\SpatieBookingPageImages;
use App\Domains\BookingPages\Infrastructure\ProvisionedCurrentBookingPage;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

final class BookingPagesServiceProvider extends ServiceProvider
{
    private const IMAGE_UPLOAD_LIMITER = 'booking-page-image-uploads';

    private const UPLOADS_PER_MINUTE = 10;

    private const UPLOADS_PER_HOUR = 60;

    public function register(): void
    {
        $this->app->bind(BookingPageRepository::class, EloquentBookingPageRepository::class);
        $this->app->bind(CurrentBookingPage::class, ProvisionedCurrentBookingPage::class);
        $this->app->bind(BookingPageImages::class, SpatieBookingPageImages::class);
    }

    public function boot(): void
    {
        Relation::enforceMorphMap(['booking_page' => BookingPageModel::class]);

        $this->registerImageUploadLimiter();

        Route::prefix('api')
            ->middleware(['api', 'auth:sanctum', 'business', 'throttle:'.self::IMAGE_UPLOAD_LIMITER])
            ->group(__DIR__.'/Infrastructure/Http/media.php');
    }

    private function registerImageUploadLimiter(): void
    {
        RateLimiter::for(self::IMAGE_UPLOAD_LIMITER, static fn (Request $request): array => [
            Limit::perMinute(self::UPLOADS_PER_MINUTE)->by('minute:'.self::limiterKeyFor($request)),
            Limit::perHour(self::UPLOADS_PER_HOUR)->by('hour:'.self::limiterKeyFor($request)),
        ]);
    }

    private static function limiterKeyFor(Request $request): string
    {
        $account = $request->user()?->getAuthIdentifier();

        return $account === null ? 'ip:'.(string) $request->ip() : 'account:'.(string) $account;
    }
}
