<?php

declare(strict_types=1);

namespace App\Domains\Phones\Contracts;

use App\Domains\Phones\Entities\Phone;
use App\Domains\Phones\ValueObjects\PhoneOwnerType;

/**
 * Every method is keyed by the owner rather than by the phone's own id: a
 * phone is only ever reached through whoever it belongs to.
 */
interface PhoneRepository
{
    /** Null when the owner has never given one, which is an ordinary state: the number is optional. */
    public function findForOwner(PhoneOwnerType $ownerType, string $ownerId): ?Phone;

    public function save(Phone $phone): void;

    public function deleteForOwner(PhoneOwnerType $ownerType, string $ownerId): void;
}
