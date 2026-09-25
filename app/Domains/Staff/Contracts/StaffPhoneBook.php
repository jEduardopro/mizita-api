<?php

declare(strict_types=1);

namespace App\Domains\Staff\Contracts;

use App\Shared\ValueObjects\PhoneNumber;

interface StaffPhoneBook
{
    public function forProfile(string $profileId): ?PhoneNumber;

    /**
     * @param  list<string>  $profileIds
     * @return array<string, PhoneNumber>
     */
    public function forProfiles(array $profileIds): array;

    /**
     * @return list<string>
     */
    public function profileIdsMatchingNumber(string $fragment): array;

    public function replaceForProfile(string $profileId, ?PhoneNumber $phone): void;
}
