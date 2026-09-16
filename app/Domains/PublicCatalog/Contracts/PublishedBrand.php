<?php

declare(strict_types=1);

namespace App\Domains\PublicCatalog\Contracts;

use App\Domains\PublicCatalog\ValueObjects\PublicBrand;

interface PublishedBrand
{
    public function forBusiness(string $businessId): PublicBrand;
}
