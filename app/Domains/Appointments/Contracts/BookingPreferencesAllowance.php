<?php

declare(strict_types=1);

namespace App\Domains\Appointments\Contracts;

interface BookingPreferencesAllowance
{
    public function includesBookingPreferences(string $businessId): bool;
}
