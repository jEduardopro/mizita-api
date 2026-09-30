<?php

declare(strict_types=1);

namespace App\Domains\PublicCatalog\Contracts;

interface PublishedCity
{
    public function forBusiness(string $businessId): ?string;
}
