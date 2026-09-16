<?php

declare(strict_types=1);

namespace App\Domains\PublicCatalog\Contracts;

use App\Domains\PublicCatalog\ValueObjects\PublicService;

interface PublishedServices
{
    /**
     * @return list<PublicService>
     */
    public function forBusiness(string $businessId): array;
}
