<?php

declare(strict_types=1);

namespace App\Domains\Services;

use App\Domains\Services\Contracts\BusinessProfile;
use App\Domains\Services\Contracts\ServiceImages;
use App\Domains\Services\Contracts\ServiceRepository;
use App\Domains\Services\Contracts\StaffDirectory;
use App\Domains\Services\Infrastructure\Eloquent\EloquentServiceRepository;
use App\Domains\Services\Infrastructure\Eloquent\Models\ServiceModel;
use App\Domains\Services\Infrastructure\Gateways\BusinessesBusinessProfile;
use App\Domains\Services\Infrastructure\Gateways\StaffStaffDirectory;
use App\Domains\Services\Infrastructure\Media\SpatieServiceImages;
use App\Domains\Services\Services\BookingLinks;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

final class ServicesServiceProvider extends ServiceProvider
{
    private const IMAGE_UPLOAD_LIMITER = 'service-image-uploads';

    private const UPLOADS_PER_MINUTE = 10;

    private const UPLOADS_PER_HOUR = 60;

    public function register(): void
    {
        $this->app->bind(ServiceRepository::class, EloquentServiceRepository::class);
        $this->app->bind(StaffDirectory::class, StaffStaffDirectory::class);
        $this->app->bind(ServiceImages::class, SpatieServiceImages::class);
        $this->app->bind(BusinessProfile::class, BusinessesBusinessProfile::class);

        $this->app->bind(
            BookingLinks::class,
            fn (): BookingLinks => new BookingLinks((string) config('app.url')),
        );
    }

    public function boot(): void
    {
        Relation::enforceMorphMap(['service' => ServiceModel::class]);

        $this->registerImageUploadLimiter();

        Route::prefix('api')
            ->middleware(['api', 'auth:sanctum', 'business'])
            ->group(__DIR__.'/Infrastructure/Http/routes.php');

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
