<?php

declare(strict_types=1);

namespace App\Domains\Appointments\Contracts;

use App\Domains\Appointments\Exceptions\AppointmentCustomerNotFound;
use App\Domains\Appointments\ValueObjects\CustomerSnapshot;

interface CustomerDirectory
{
    /**
     * @throws AppointmentCustomerNotFound
     */
    public function describe(string $businessId, string $customerId): CustomerSnapshot;

    /**
     * @param  list<string>  $customerIds
     * @return array<string, CustomerSnapshot>
     */
    public function describeMany(string $businessId, array $customerIds): array;
}
