<?php

declare(strict_types=1);

namespace App\Domains\Phones\Contracts;

use App\Domains\Phones\Entities\Phone;
use App\Domains\Phones\ValueObjects\PhoneOwnerType;

/**
 * Port for Phone persistence. It speaks entities, never Eloquent models or
 * query builders, so use cases stay independent of the database.
 *
 * Every method is keyed by the owner rather than by the phone's own id: a
 * phone is only ever reached through whoever it belongs to.
 */
interface PhoneRepository
{
    /**
     * The owner's phone, or null when they have never given one. A missing
     * phone is an ordinary state here, not a failure: the number is optional.
     */
    public function findForOwner(PhoneOwnerType $ownerType, string $ownerId): ?Phone;

    public function save(Phone $phone): void;

    /**
     * Soft deletes the owner's phone, if there is one. Reads stop returning
     * it; the row is kept.
     */
    public function deleteForOwner(PhoneOwnerType $ownerType, string $ownerId): void;
}
