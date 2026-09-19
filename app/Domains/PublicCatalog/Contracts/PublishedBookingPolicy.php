<?php

declare(strict_types=1);

namespace App\Domains\PublicCatalog\Contracts;

use App\Domains\PublicCatalog\ValueObjects\PublicBookingPolicy;

interface PublishedBookingPolicy
{
    public function forBusiness(string $businessId): ?PublicBookingPolicy;
}
