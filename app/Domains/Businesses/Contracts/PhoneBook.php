<?php

declare(strict_types=1);

namespace App\Domains\Businesses\Contracts;

use App\Shared\ValueObjects\PhoneNumber;

interface PhoneBook
{
    public function forBusiness(string $businessId): ?PhoneNumber;

    public function attachToBusiness(string $businessId, PhoneNumber $phone): void;

    public function replaceForBusiness(string $businessId, ?PhoneNumber $phone): void;
}
