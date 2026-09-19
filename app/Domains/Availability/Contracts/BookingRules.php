<?php

declare(strict_types=1);

namespace App\Domains\Availability\Contracts;

use App\Domains\Availability\ValueObjects\SlotRules;

interface BookingRules
{
    public function forBusiness(string $businessId): SlotRules;
}
