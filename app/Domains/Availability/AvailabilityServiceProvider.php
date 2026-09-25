<?php

declare(strict_types=1);

namespace App\Domains\Availability;

use App\Domains\Availability\Contracts\BookableServices;
use App\Domains\Availability\Contracts\BookedIntervals;
use App\Domains\Availability\Contracts\BookingRules;
use App\Domains\Availability\Contracts\BusinessClock;
use App\Domains\Availability\Contracts\ExternalBusyIntervals;
use App\Domains\Availability\Contracts\ScheduleRuleRepository;
use App\Domains\Availability\Contracts\StaffMembership;
use App\Domains\Availability\Contracts\StaffRoster;
use App\Domains\Availability\Contracts\StaffSchedules;
use App\Domains\Availability\Infrastructure\Eloquent\EloquentScheduleRuleRepository;
use App\Domains\Availability\Infrastructure\Gateways\AppointmentsBookedIntervals;
use App\Domains\Availability\Infrastructure\Gateways\BookingPoliciesBookingRules;
use App\Domains\Availability\Infrastructure\Gateways\BusinessesBusinessClock;
use App\Domains\Availability\Infrastructure\Gateways\IntegrationsExternalBusyIntervals;
use App\Domains\Availability\Infrastructure\Gateways\RuleBasedStaffSchedules;
use App\Domains\Availability\Infrastructure\Gateways\ServicesBookableServices;
use App\Domains\Availability\Infrastructure\Gateways\StaffStaffMembership;
use App\Domains\Availability\Infrastructure\Gateways\StaffStaffRoster;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

final class AvailabilityServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(ScheduleRuleRepository::class, EloquentScheduleRuleRepository::class);
        $this->app->bind(BookableServices::class, ServicesBookableServices::class);
        $this->app->bind(StaffSchedules::class, RuleBasedStaffSchedules::class);
        $this->app->bind(BookedIntervals::class, AppointmentsBookedIntervals::class);
        $this->app->bind(ExternalBusyIntervals::class, IntegrationsExternalBusyIntervals::class);
        $this->app->bind(BookingRules::class, BookingPoliciesBookingRules::class);
        $this->app->bind(BusinessClock::class, BusinessesBusinessClock::class);
        $this->app->bind(StaffMembership::class, StaffStaffMembership::class);
        $this->app->bind(StaffRoster::class, StaffStaffRoster::class);
    }

    public function boot(): void
    {
        Route::prefix('api')
            ->middleware(['api', 'auth:sanctum', 'business'])
            ->group(__DIR__.'/Infrastructure/Http/routes.php');
    }
}
