<?php

declare(strict_types=1);

namespace App\Domains\Businesses\Contracts;

use App\Domains\Businesses\Exceptions\OwnerAlreadyHasBusiness;

interface OwnerRegistrar
{
    /**
     * @return list<object>
     *
     * @throws OwnerAlreadyHasBusiness
     */
    public function registerOwner(string $businessId, string $accountId): array;
}
