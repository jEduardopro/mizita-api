<?php

declare(strict_types=1);

namespace App\Domains\Staff\Contracts;

use App\Shared\ValueObjects\PhoneNumber;

interface StaffPhoneBook
{
    public function forProfile(string $profileId): ?PhoneNumber;

    public function replaceForProfile(string $profileId, ?PhoneNumber $phone): void;
}
