<?php

declare(strict_types=1);

namespace App\Domains\PublicCatalog\Contracts;

use App\Domains\PublicCatalog\ValueObjects\PublicLocation;

interface PublishedLocation
{
    public function forBusiness(string $businessId): ?PublicLocation;
}
