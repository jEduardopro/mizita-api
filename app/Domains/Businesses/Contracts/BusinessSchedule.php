<?php

declare(strict_types=1);

namespace App\Domains\Businesses\Contracts;

use App\Domains\Businesses\ValueObjects\BusinessScheduleEntry;

interface BusinessSchedule
{
    /**
     * @return list<BusinessScheduleEntry>
     */
    public function forBusiness(string $businessId): array;

    /**
     * @param  list<BusinessScheduleEntry>  $entries
     */
    public function replaceForBusiness(string $businessId, array $entries): void;
}
