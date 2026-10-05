<?php

declare(strict_types=1);

namespace App\Domains\PublicCatalog\Contracts;

interface PublishedBanner
{
    public function originalUrlForBusiness(string $businessId): ?string;
}
