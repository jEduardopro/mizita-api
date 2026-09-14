<?php

declare(strict_types=1);

namespace App\Domains\Phones\Contracts;

use App\Domains\Phones\Entities\Phone;
use App\Domains\Phones\ValueObjects\PhoneOwnerType;

interface PhoneRepository
{
    public function findForOwner(PhoneOwnerType $ownerType, string $ownerId): ?Phone;

    public function save(Phone $phone): void;

    public function deleteForOwner(PhoneOwnerType $ownerType, string $ownerId): void;
}
