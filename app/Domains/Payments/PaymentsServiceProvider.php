<?php

declare(strict_types=1);

namespace App\Domains\Payments;

use App\Domains\Payments\Contracts\AppointmentDirectory;
use App\Domains\Payments\Contracts\BusinessPaymentMethods;
use App\Domains\Payments\Contracts\BusinessProfile;
use App\Domains\Payments\Contracts\BusinessTimezone;
use App\Domains\Payments\Contracts\CalendarAccess;
use App\Domains\Payments\Contracts\PaymentMethodCatalog;
use App\Domains\Payments\Contracts\PaymentReports;
use App\Domains\Payments\Contracts\PaymentRepository;
use App\Domains\Payments\Contracts\ServiceCatalog;
use App\Domains\Payments\Infrastructure\Eloquent\EloquentBusinessPaymentMethods;
use App\Domains\Payments\Infrastructure\Eloquent\EloquentPaymentMethodCatalog;
use App\Domains\Payments\Infrastructure\Eloquent\EloquentPaymentReports;
use App\Domains\Payments\Infrastructure\Eloquent\EloquentPaymentRepository;
use App\Domains\Payments\Infrastructure\Gateways\AppointmentsAppointmentDirectory;
use App\Domains\Payments\Infrastructure\Gateways\BusinessesBusinessProfile;
use App\Domains\Payments\Infrastructure\Gateways\BusinessesBusinessTimezone;
use App\Domains\Payments\Infrastructure\Gateways\ServicesServiceCatalog;
use App\Domains\Payments\Infrastructure\Gateways\StaffCalendarAccess;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

final class PaymentsServiceProvider extends ServiceProvider
{
    private const OWNER_API_MIDDLEWARE = ['api', 'auth:sanctum', 'business', 'owner.api'];

    public function register(): void
    {
        $this->app->bind(PaymentRepository::class, EloquentPaymentRepository::class);
        $this->app->bind(PaymentMethodCatalog::class, EloquentPaymentMethodCatalog::class);
        $this->app->bind(BusinessPaymentMethods::class, EloquentBusinessPaymentMethods::class);
        $this->app->bind(AppointmentDirectory::class, AppointmentsAppointmentDirectory::class);
        $this->app->bind(ServiceCatalog::class, ServicesServiceCatalog::class);
        $this->app->bind(BusinessProfile::class, BusinessesBusinessProfile::class);
        $this->app->bind(CalendarAccess::class, StaffCalendarAccess::class);
        $this->app->bind(PaymentReports::class, EloquentPaymentReports::class);
        $this->app->bind(BusinessTimezone::class, BusinessesBusinessTimezone::class);
    }

    public function boot(): void
    {
        Route::prefix('api')
            ->middleware(['api', 'auth:sanctum', 'business'])
            ->group(__DIR__.'/Infrastructure/Http/routes.php');

        Route::prefix('api')
            ->middleware(self::OWNER_API_MIDDLEWARE)
            ->group(__DIR__.'/Infrastructure/Http/report-routes.php');
    }
}
