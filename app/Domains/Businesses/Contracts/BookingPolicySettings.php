<?php

declare(strict_types=1);

namespace App\Domains\Businesses\Contracts;

use App\Domains\Businesses\ValueObjects\BookingPolicyPreferences;
use App\Domains\Businesses\ValueObjects\BookingPolicySnapshot;

interface BookingPolicySettings
{
    public function forBusiness(string $businessId): BookingPolicySnapshot;

    public function applyTo(string $businessId, BookingPolicyPreferences $preferences): void;
}
