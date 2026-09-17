<?php

declare(strict_types=1);

namespace App\Domains\Phones\Contracts;

use App\Domains\Phones\Entities\Phone;
use App\Domains\Phones\ValueObjects\PhoneNumberFragment;
use App\Domains\Phones\ValueObjects\PhoneOwnerType;
use App\Shared\ValueObjects\PhoneNumber;

interface PhoneRepository
{
    public function findForOwner(PhoneOwnerType $ownerType, string $ownerId): ?Phone;

    /**
     * @param  list<string>  $ownerIds  owner uuids
     * @return array<string, Phone> keyed by owner uuid, missing owners absent
     */
    public function findForOwners(PhoneOwnerType $ownerType, array $ownerIds): array;

    /**
     * @return list<string> owner uuids holding that number
     */
    public function ownerIdsWithNumber(PhoneOwnerType $ownerType, PhoneNumber $number): array;

    /**
     * @return list<string> owner uuids whose number contains that fragment
     */
    public function ownerIdsMatchingNumber(PhoneOwnerType $ownerType, PhoneNumberFragment $fragment): array;

    public function save(Phone $phone): void;

    public function deleteForOwner(PhoneOwnerType $ownerType, string $ownerId): void;
}
