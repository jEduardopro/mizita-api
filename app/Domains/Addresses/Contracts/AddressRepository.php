<?php

declare(strict_types=1);

namespace App\Domains\Addresses\Contracts;

use App\Domains\Addresses\Entities\Address;
use App\Domains\Addresses\ValueObjects\AddressOwnerType;

interface AddressRepository
{
    public function findForOwner(AddressOwnerType $ownerType, string $ownerId): ?Address;

    public function save(Address $address): void;

    public function deleteForOwner(AddressOwnerType $ownerType, string $ownerId): void;
}
