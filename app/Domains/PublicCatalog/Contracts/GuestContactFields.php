<?php

declare(strict_types=1);

namespace App\Domains\PublicCatalog\Contracts;

use App\Domains\PublicCatalog\ValueObjects\GuestFormFields;

interface GuestContactFields
{
    public function forBusiness(string $businessId): GuestFormFields;
}
