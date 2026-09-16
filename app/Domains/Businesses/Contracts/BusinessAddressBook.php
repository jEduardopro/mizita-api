<?php

declare(strict_types=1);

namespace App\Domains\Businesses\Contracts;

use App\Domains\Businesses\ValueObjects\BusinessAddressSnapshot;

interface BusinessAddressBook
{
    public function forBusiness(string $businessId): ?BusinessAddressSnapshot;

    public function replaceForBusiness(string $businessId, BusinessAddressSnapshot $address): void;

    public function removeForBusiness(string $businessId): void;
}
