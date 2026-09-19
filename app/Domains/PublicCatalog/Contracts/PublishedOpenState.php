<?php

declare(strict_types=1);

namespace App\Domains\PublicCatalog\Contracts;

use App\Domains\PublicCatalog\ValueObjects\PublicOpenState;

interface PublishedOpenState
{
    public function forBusiness(string $businessId): PublicOpenState;
}
