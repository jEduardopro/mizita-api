<?php

declare(strict_types=1);

namespace App\Domains\Customers\Contracts;

use App\Domains\Customers\ValueObjects\CustomerAddressSnapshot;

interface CustomerAddressBook
{
    public function forCustomer(string $customerId): ?CustomerAddressSnapshot;

    public function replaceForCustomer(string $customerId, CustomerAddressSnapshot $address): void;

    public function removeForCustomer(string $customerId): void;
}
