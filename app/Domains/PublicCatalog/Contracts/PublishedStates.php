<?php

declare(strict_types=1);

namespace App\Domains\PublicCatalog\Contracts;

use App\Domains\PublicCatalog\ValueObjects\PublicState;
use App\Shared\ValueObjects\CountryCode;

interface PublishedStates
{
    /**
     * @return list<PublicState>
     */
    public function activeFor(CountryCode $country): array;
}
