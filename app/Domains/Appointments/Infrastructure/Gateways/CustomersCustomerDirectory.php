<?php

declare(strict_types=1);

namespace App\Domains\Appointments\Infrastructure\Gateways;

use App\Domains\Appointments\Contracts\CustomerDirectory;
use App\Domains\Appointments\Exceptions\AppointmentCustomerNotFound;
use App\Domains\Appointments\ValueObjects\CustomerSnapshot;
use App\Domains\Customers\Contracts\CustomerRepository;
use App\Domains\Customers\Entities\Customer;
use App\Domains\Customers\Exceptions\CustomerNotFound;

final class CustomersCustomerDirectory implements CustomerDirectory
{
    public function __construct(
        private readonly CustomerRepository $customers,
    ) {}

    public function describe(string $businessId, string $customerId): CustomerSnapshot
    {
        try {
            return self::snapshotOf($this->customers->findIncludingArchived($businessId, $customerId));
        } catch (CustomerNotFound $missing) {
            throw AppointmentCustomerNotFound::withId($customerId, $missing);
        }
    }

    /**
     * @param  list<string>  $customerIds
     * @return array<string, CustomerSnapshot>
     */
    public function describeMany(string $businessId, array $customerIds): array
    {
        $snapshots = [];

        foreach (array_unique($customerIds) as $customerId) {
            $snapshots[$customerId] = $this->describe($businessId, $customerId);
        }

        return $snapshots;
    }

    private static function snapshotOf(Customer $customer): CustomerSnapshot
    {
        return new CustomerSnapshot(
            id: $customer->id,
            name: $customer->name(),
            email: $customer->email()?->value,
        );
    }
}
