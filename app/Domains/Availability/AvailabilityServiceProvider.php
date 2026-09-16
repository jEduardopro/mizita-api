<?php

declare(strict_types=1);

namespace App\Domains\Availability;

use App\Domains\Availability\Contracts\ScheduleRuleRepository;
use App\Domains\Availability\Infrastructure\Eloquent\EloquentScheduleRuleRepository;
use Illuminate\Support\ServiceProvider;

final class AvailabilityServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(ScheduleRuleRepository::class, EloquentScheduleRuleRepository::class);
    }

    // No boot() and no routes.php yet: business hours are written through the business
    // settings endpoint, and the staff schedule screen has not shipped.
}
