<?php

declare(strict_types=1);

namespace App\Domains\Customers\Contracts;

use App\Shared\ValueObjects\PhoneNumber;

interface CustomerPhoneBook
{
    public function forCustomer(string $customerId): ?PhoneNumber;

    /**
     * @param  list<string>  $customerIds
     * @return array<string, PhoneNumber>
     */
    public function forCustomers(array $customerIds): array;

    public function replaceForCustomer(string $customerId, ?PhoneNumber $phone): void;

    public function removeForCustomer(string $customerId): void;

    /**
     * @return list<string>
     */
    public function customerIdsWithNumber(PhoneNumber $number): array;

    /**
     * @return list<string> customers whose number contains the digits typed in $fragment
     */
    public function customerIdsMatchingNumber(string $fragment): array;
}
