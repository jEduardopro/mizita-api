<?php

declare(strict_types=1);

namespace App\Domains\PublicCatalog\Contracts;

use App\Domains\PublicCatalog\ValueObjects\PublicAvailableDay;
use App\Domains\PublicCatalog\ValueObjects\PublicSlotQuery;

interface PublishedSlots
{
    /**
     * @return list<PublicAvailableDay>
     */
    public function forBusiness(string $businessId, PublicSlotQuery $query): array;
}
