<?php

declare(strict_types=1);

namespace App\Domains\Availability;

use App\Domains\Availability\Contracts\BookableServices;
use App\Domains\Availability\Contracts\BookedIntervals;
use App\Domains\Availability\Contracts\BookingRules;
use App\Domains\Availability\Contracts\BusinessClock;
use App\Domains\Availability\Contracts\ScheduleRuleRepository;
use App\Domains\Availability\Contracts\StaffSchedules;
use App\Domains\Availability\Infrastructure\Eloquent\EloquentScheduleRuleRepository;
use App\Domains\Availability\Infrastructure\Gateways\AppointmentsBookedIntervals;
use App\Domains\Availability\Infrastructure\Gateways\BookingPoliciesBookingRules;
use App\Domains\Availability\Infrastructure\Gateways\BusinessesBusinessClock;
use App\Domains\Availability\Infrastructure\Gateways\RuleBasedStaffSchedules;
use App\Domains\Availability\Infrastructure\Gateways\ServicesBookableServices;
use Illuminate\Support\ServiceProvider;

final class AvailabilityServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(ScheduleRuleRepository::class, EloquentScheduleRuleRepository::class);
        $this->app->bind(BookableServices::class, ServicesBookableServices::class);
        $this->app->bind(StaffSchedules::class, RuleBasedStaffSchedules::class);
        $this->app->bind(BookedIntervals::class, AppointmentsBookedIntervals::class);
        $this->app->bind(BookingRules::class, BookingPoliciesBookingRules::class);
        $this->app->bind(BusinessClock::class, BusinessesBusinessClock::class);
    }

    // No boot() and no routes.php yet: business hours are written through the business
    // settings endpoint, and the staff schedule screen has not shipped.
}
