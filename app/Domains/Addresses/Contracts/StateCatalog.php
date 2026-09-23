<?php

declare(strict_types=1);

namespace App\Domains\Addresses\Contracts;

use App\Domains\Addresses\Entities\State;
use App\Shared\ValueObjects\CountryCode;

interface StateCatalog
{
    /**
     * @return list<State>
     */
    public function allActiveFor(CountryCode $country): array;

    public function findActiveByNameOrCode(CountryCode $country, string $nameOrCode): ?State;
}
