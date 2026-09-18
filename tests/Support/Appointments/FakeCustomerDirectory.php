<?php

declare(strict_types=1);

namespace Tests\Support\Appointments;

use App\Domains\Appointments\Contracts\CustomerDirectory;
use App\Domains\Appointments\Exceptions\AppointmentCustomerNotFound;
use App\Domains\Appointments\ValueObjects\CustomerSnapshot;

final class FakeCustomerDirectory implements CustomerDirectory
{
    /**
     * @var array<string, array<string, CustomerSnapshot>>
     */
    private array $customersByBusiness = [];

    /**
     * @var list<array{businessId: string, customerId: string}>
     */
    public array $reads = [];

    /**
     * @var list<array{businessId: string, customerIds: list<string>}>
     */
    public array $batchReads = [];

    public function __construct(
        public readonly AppointmentJournal $journal = new AppointmentJournal,
    ) {}

    public static function of(string $businessId, CustomerSnapshot ...$customers): self
    {
        return (new self)->add($businessId, ...$customers);
    }

    public function add(string $businessId, CustomerSnapshot ...$customers): self
    {
        foreach ($customers as $customer) {
            $this->customersByBusiness[$businessId][$customer->id] = $customer;
        }

        return $this;
    }

    public function describe(string $businessId, string $customerId): CustomerSnapshot
    {
        $this->journal->record('customers.describe');
        $this->reads[] = ['businessId' => $businessId, 'customerId' => $customerId];

        return $this->customersByBusiness[$businessId][$customerId]
            ?? throw AppointmentCustomerNotFound::withId($customerId);
    }

    /**
     * @param  list<string>  $customerIds
     * @return array<string, CustomerSnapshot>
     */
    public function describeMany(string $businessId, array $customerIds): array
    {
        $this->journal->record('customers.describeMany');
        $this->batchReads[] = ['businessId' => $businessId, 'customerIds' => array_values($customerIds)];

        $known = $this->customersByBusiness[$businessId] ?? [];
        $found = [];

        foreach ($customerIds as $customerId) {
            if (isset($known[$customerId])) {
                $found[$customerId] = $known[$customerId];
            }
        }

        return $found;
    }
}
