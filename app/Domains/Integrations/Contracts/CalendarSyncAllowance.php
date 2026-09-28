<?php

declare(strict_types=1);

namespace App\Domains\Integrations\Contracts;

interface CalendarSyncAllowance
{
    public function includesCalendarSync(string $businessId): bool;
}
