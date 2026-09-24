<?php

declare(strict_types=1);

namespace App\Domains\Businesses\Contracts;

use App\Domains\Businesses\Exceptions\OwnerAlreadyHasBusiness;
use App\Domains\Businesses\ValueObjects\OwnerRegistration;

interface OwnerRegistrar
{
    /**
     * @throws OwnerAlreadyHasBusiness
     */
    public function registerOwner(string $businessId, string $accountId): OwnerRegistration;
}
