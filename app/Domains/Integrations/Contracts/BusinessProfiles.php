<?php

declare(strict_types=1);

namespace App\Domains\Integrations\Contracts;

use App\Domains\Integrations\Exceptions\CalendarBusinessNotFound;
use App\Domains\Integrations\ValueObjects\BusinessCalendarProfile;

interface BusinessProfiles
{
    /**
     * @throws CalendarBusinessNotFound
     */
    public function profileOf(string $businessId): BusinessCalendarProfile;
}
