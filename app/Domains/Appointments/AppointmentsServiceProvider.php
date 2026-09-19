<?php

declare(strict_types=1);

namespace App\Domains\Appointments;

use App\Domains\Appointments\Contracts\AppointmentRepository;
use App\Domains\Appointments\Contracts\BookableSlots;
use App\Domains\Appointments\Contracts\CancellationPolicy;
use App\Domains\Appointments\Contracts\CustomerDirectory;
use App\Domains\Appointments\Contracts\ManageTokenFactory;
use App\Domains\Appointments\Contracts\OpeningHours;
use App\Domains\Appointments\Contracts\ReferenceCodeGenerator;
use App\Domains\Appointments\Contracts\ServiceCatalog;
use App\Domains\Appointments\Contracts\StaffDirectory;
use App\Domains\Appointments\Infrastructure\Eloquent\EloquentAppointmentRepository;
use App\Domains\Appointments\Infrastructure\Gateways\AvailabilityBookableSlots;
use App\Domains\Appointments\Infrastructure\Gateways\AvailabilityOpeningHours;
use App\Domains\Appointments\Infrastructure\Gateways\BookingPoliciesCancellationPolicy;
use App\Domains\Appointments\Infrastructure\Gateways\CustomersCustomerDirectory;
use App\Domains\Appointments\Infrastructure\Gateways\ServicesServiceCatalog;
use App\Domains\Appointments\Infrastructure\Gateways\StaffStaffDirectory;
use App\Domains\Appointments\Infrastructure\RandomManageTokenFactory;
use App\Domains\Appointments\Infrastructure\RandomReferenceCodeGenerator;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

final class AppointmentsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(AppointmentRepository::class, EloquentAppointmentRepository::class);
        $this->app->bind(ServiceCatalog::class, ServicesServiceCatalog::class);
        $this->app->bind(CustomerDirectory::class, CustomersCustomerDirectory::class);
        $this->app->bind(StaffDirectory::class, StaffStaffDirectory::class);
        $this->app->bind(BookableSlots::class, AvailabilityBookableSlots::class);
        $this->app->bind(OpeningHours::class, AvailabilityOpeningHours::class);
        $this->app->bind(CancellationPolicy::class, BookingPoliciesCancellationPolicy::class);
        $this->app->bind(ReferenceCodeGenerator::class, RandomReferenceCodeGenerator::class);
        $this->app->bind(ManageTokenFactory::class, RandomManageTokenFactory::class);
    }

    public function boot(): void
    {
        Route::prefix('api')
            ->middleware(['api', 'auth:sanctum', 'business'])
            ->group(__DIR__.'/Infrastructure/Http/routes.php');
    }
}
