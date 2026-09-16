<?php

declare(strict_types=1);

namespace App\Domains\PublicCatalog\Contracts;

use App\Domains\PublicCatalog\ValueObjects\PublicScheduleEntry;

interface PublishedSchedule
{
    /**
     * @return list<PublicScheduleEntry>
     */
    public function forBusiness(string $businessId): array;
}
