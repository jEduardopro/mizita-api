<?php

declare(strict_types=1);

namespace App\Domains\PublicCatalog\Contracts;

use App\Domains\PublicCatalog\ValueObjects\PublicContact;

interface PublishedContact
{
    public function forBusiness(string $businessId): PublicContact;
}
