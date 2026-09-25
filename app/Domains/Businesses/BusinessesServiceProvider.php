<?php

declare(strict_types=1);

namespace App\Domains\Businesses;

use App\Domains\Businesses\Contracts\BookingPageSettings;
use App\Domains\Businesses\Contracts\BookingPolicySettings;
use App\Domains\Businesses\Contracts\BusinessAddressBook;
use App\Domains\Businesses\Contracts\BusinessLinkList;
use App\Domains\Businesses\Contracts\BusinessLogo;
use App\Domains\Businesses\Contracts\BusinessRepository;
use App\Domains\Businesses\Contracts\BusinessSchedule;
use App\Domains\Businesses\Contracts\IndustryCatalog;
use App\Domains\Businesses\Contracts\OwnerRegistrar;
use App\Domains\Businesses\Contracts\PaymentMethodProvisioner;
use App\Domains\Businesses\Contracts\PhoneBook;
use App\Domains\Businesses\Contracts\RoleProvisioner;
use App\Domains\Businesses\Contracts\ScheduleProvisioner;
use App\Domains\Businesses\Contracts\TeamSignOut;
use App\Domains\Businesses\Contracts\TenantDataEraser;
use App\Domains\Businesses\Infrastructure\Console\PurgeClosedBusinessesCommand;
use App\Domains\Businesses\Infrastructure\Eloquent\EloquentBusinessRepository;
use App\Domains\Businesses\Infrastructure\Eloquent\Models\BusinessModel;
use App\Domains\Businesses\Infrastructure\Gateways\AddressesBusinessAddressBook;
use App\Domains\Businesses\Infrastructure\Gateways\AvailabilityBusinessSchedule;
use App\Domains\Businesses\Infrastructure\Gateways\AvailabilityScheduleProvisioner;
use App\Domains\Businesses\Infrastructure\Gateways\BookingPagesBookingPageSettings;
use App\Domains\Businesses\Infrastructure\Gateways\BookingPoliciesBookingPolicySettings;
use App\Domains\Businesses\Infrastructure\Gateways\EloquentBusinessTeamKey;
use App\Domains\Businesses\Infrastructure\Gateways\IndustriesIndustryCatalog;
use App\Domains\Businesses\Infrastructure\Gateways\LinksBusinessLinkList;
use App\Domains\Businesses\Infrastructure\Gateways\PaymentsPaymentMethodProvisioner;
use App\Domains\Businesses\Infrastructure\Gateways\PhonesPhoneBook;
use App\Domains\Businesses\Infrastructure\Gateways\StaffOwnerRegistrar;
use App\Domains\Businesses\Infrastructure\Gateways\StaffRoleProvisioner;
use App\Domains\Businesses\Infrastructure\Gateways\StaffTeamSignOut;
use App\Domains\Businesses\Infrastructure\Media\SpatieBusinessLogo;
use App\Domains\Businesses\Infrastructure\Purge\DatabaseTenantDataEraser;
use App\Shared\Contracts\BusinessTeamKey;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

final class BusinessesServiceProvider extends ServiceProvider
{
    private const IMAGE_UPLOAD_LIMITER = 'business-image-uploads';

    private const UPLOADS_PER_MINUTE = 10;

    private const UPLOADS_PER_HOUR = 60;

    public function register(): void
    {
        $this->app->bind(BusinessRepository::class, EloquentBusinessRepository::class);
        $this->app->bind(IndustryCatalog::class, IndustriesIndustryCatalog::class);
        $this->app->bind(OwnerRegistrar::class, StaffOwnerRegistrar::class);
        $this->app->bind(RoleProvisioner::class, StaffRoleProvisioner::class);
        $this->app->bind(PaymentMethodProvisioner::class, PaymentsPaymentMethodProvisioner::class);
        $this->app->bind(PhoneBook::class, PhonesPhoneBook::class);
        $this->app->bind(BusinessTeamKey::class, EloquentBusinessTeamKey::class);
        $this->app->bind(BusinessLogo::class, SpatieBusinessLogo::class);
        $this->app->bind(BusinessAddressBook::class, AddressesBusinessAddressBook::class);
        $this->app->bind(BusinessLinkList::class, LinksBusinessLinkList::class);
        $this->app->bind(BusinessSchedule::class, AvailabilityBusinessSchedule::class);
        $this->app->bind(ScheduleProvisioner::class, AvailabilityScheduleProvisioner::class);
        $this->app->bind(BookingPageSettings::class, BookingPagesBookingPageSettings::class);
        $this->app->bind(BookingPolicySettings::class, BookingPoliciesBookingPolicySettings::class);
        $this->app->bind(TeamSignOut::class, StaffTeamSignOut::class);
        $this->app->bind(TenantDataEraser::class, DatabaseTenantDataEraser::class);
    }

    public function boot(): void
    {
        Relation::enforceMorphMap(['business' => BusinessModel::class]);

        $this->registerImageUploadLimiter();

        if ($this->app->runningInConsole()) {
            $this->commands([PurgeClosedBusinessesCommand::class]);
        }

        Route::prefix('api')
            ->middleware(['api', 'auth:sanctum'])
            ->group(__DIR__.'/Infrastructure/Http/onboarding.php');

        Route::prefix('api')
            ->middleware(['api', 'auth:sanctum'])
            ->group(__DIR__.'/Infrastructure/Http/account.php');

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
