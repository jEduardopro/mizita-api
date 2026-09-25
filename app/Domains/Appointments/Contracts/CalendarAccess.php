<?php

declare(strict_types=1);

namespace App\Domains\Appointments\Contracts;

use App\Domains\Appointments\Exceptions\CalendarNotAccessible;
use App\Domains\Appointments\ValueObjects\CalendarScope;

interface CalendarAccess
{
    /**
     * @throws CalendarNotAccessible
     */
    public function scopeFor(string $businessId, string $accountId): CalendarScope;
}
