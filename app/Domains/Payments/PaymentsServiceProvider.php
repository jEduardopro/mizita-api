<?php

declare(strict_types=1);

namespace App\Domains\Payments;

use App\Domains\Payments\Contracts\AppointmentDirectory;
use App\Domains\Payments\Contracts\BusinessPaymentMethods;
use App\Domains\Payments\Contracts\BusinessProfile;
use App\Domains\Payments\Contracts\CalendarAccess;
use App\Domains\Payments\Contracts\PaymentMethodCatalog;
use App\Domains\Payments\Contracts\PaymentRepository;
use App\Domains\Payments\Contracts\ServiceCatalog;
use App\Domains\Payments\Infrastructure\Eloquent\EloquentBusinessPaymentMethods;
use App\Domains\Payments\Infrastructure\Eloquent\EloquentPaymentMethodCatalog;
use App\Domains\Payments\Infrastructure\Eloquent\EloquentPaymentRepository;
use App\Domains\Payments\Infrastructure\Gateways\AppointmentsAppointmentDirectory;
use App\Domains\Payments\Infrastructure\Gateways\BusinessesBusinessProfile;
use App\Domains\Payments\Infrastructure\Gateways\ServicesServiceCatalog;
use App\Domains\Payments\Infrastructure\Gateways\StaffCalendarAccess;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

final class PaymentsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(PaymentRepository::class, EloquentPaymentRepository::class);
        $this->app->bind(PaymentMethodCatalog::class, EloquentPaymentMethodCatalog::class);
        $this->app->bind(BusinessPaymentMethods::class, EloquentBusinessPaymentMethods::class);
        $this->app->bind(AppointmentDirectory::class, AppointmentsAppointmentDirectory::class);
        $this->app->bind(ServiceCatalog::class, ServicesServiceCatalog::class);
        $this->app->bind(BusinessProfile::class, BusinessesBusinessProfile::class);
        $this->app->bind(CalendarAccess::class, StaffCalendarAccess::class);
    }

    public function boot(): void
    {
        Route::prefix('api')
            ->middleware(['api', 'auth:sanctum', 'business'])
            ->group(__DIR__.'/Infrastructure/Http/routes.php');
    }
}
