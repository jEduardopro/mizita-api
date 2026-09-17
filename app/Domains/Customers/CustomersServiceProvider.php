<?php

declare(strict_types=1);

namespace App\Domains\Customers;

use App\Domains\Customers\Contracts\CustomerAddressBook;
use App\Domains\Customers\Contracts\CustomerPhoneBook;
use App\Domains\Customers\Contracts\CustomerPhotos;
use App\Domains\Customers\Contracts\CustomerRepository;
use App\Domains\Customers\Infrastructure\Eloquent\EloquentCustomerRepository;
use App\Domains\Customers\Infrastructure\Eloquent\Models\CustomerModel;
use App\Domains\Customers\Infrastructure\Gateways\AddressesCustomerAddressBook;
use App\Domains\Customers\Infrastructure\Gateways\PhonesCustomerPhoneBook;
use App\Domains\Customers\Infrastructure\Media\SpatieCustomerPhotos;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

final class CustomersServiceProvider extends ServiceProvider
{
    private const PHOTO_UPLOAD_LIMITER = 'customer-photo-uploads';

    private const UPLOADS_PER_MINUTE = 10;

    private const UPLOADS_PER_HOUR = 60;

    public function register(): void
    {
        $this->app->bind(CustomerRepository::class, EloquentCustomerRepository::class);
        $this->app->bind(CustomerPhoneBook::class, PhonesCustomerPhoneBook::class);
        $this->app->bind(CustomerAddressBook::class, AddressesCustomerAddressBook::class);
        $this->app->bind(CustomerPhotos::class, SpatieCustomerPhotos::class);
    }

    public function boot(): void
    {
        Relation::enforceMorphMap(['customer' => CustomerModel::class]);

        $this->registerPhotoUploadLimiter();

        Route::prefix('api')
            ->middleware(['api', 'auth:sanctum', 'business'])
            ->group(__DIR__.'/Infrastructure/Http/routes.php');

        Route::prefix('api')
            ->middleware(['api', 'auth:sanctum', 'business', 'throttle:'.self::PHOTO_UPLOAD_LIMITER])
            ->group(__DIR__.'/Infrastructure/Http/media.php');
    }

    private function registerPhotoUploadLimiter(): void
    {
        RateLimiter::for(self::PHOTO_UPLOAD_LIMITER, static fn (Request $request): array => [
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
