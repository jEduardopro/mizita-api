<?php

declare(strict_types=1);

namespace App\Domains\Phones\Contracts;

use App\Domains\Phones\Entities\Phone;
use App\Domains\Phones\ValueObjects\PhoneNumberFragment;
use App\Domains\Phones\ValueObjects\PhoneOwnerType;
use App\Shared\ValueObjects\PhoneNumber;

interface PhoneRepository
{
    public const MAXIMUM_OWNER_MATCHES = 500;

    public function findForOwner(PhoneOwnerType $ownerType, string $ownerId): ?Phone;

    /**
     * @param  list<string>  $ownerIds  owner uuids
     * @return array<string, Phone> keyed by owner uuid, missing owners absent
     */
    public function findForOwners(PhoneOwnerType $ownerType, array $ownerIds): array;

    /**
     * @return list<string> uuids of that business's owners holding that number, at most MAXIMUM_OWNER_MATCHES
     */
    public function ownerIdsWithNumber(PhoneOwnerType $ownerType, string $businessId, PhoneNumber $number): array;

    /**
     * @return list<string> uuids of that business's owners whose number contains that fragment, at most MAXIMUM_OWNER_MATCHES
     */
    public function ownerIdsMatchingNumber(PhoneOwnerType $ownerType, string $businessId, PhoneNumberFragment $fragment): array;

    public function save(Phone $phone): void;

    public function deleteForOwner(PhoneOwnerType $ownerType, string $ownerId): void;
}
