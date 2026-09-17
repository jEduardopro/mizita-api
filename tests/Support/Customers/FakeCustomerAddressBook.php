<?php

declare(strict_types=1);

namespace Tests\Support\Customers;

use App\Domains\Customers\Contracts\CustomerAddressBook;
use App\Domains\Customers\ValueObjects\CustomerAddressSnapshot;
use Throwable;

final class FakeCustomerAddressBook implements CustomerAddressBook
{
    /**
     * @var array<string, CustomerAddressSnapshot>
     */
    private array $addresses = [];

    private ?Throwable $replaceFailure = null;

    /**
     * @var list<string>
     */
    public array $reads = [];

    /**
     * @var list<array{customerId: string, address: CustomerAddressSnapshot}>
     */
    public array $calls = [];

    /**
     * @var list<array{customerId: string, address: CustomerAddressSnapshot}>
     */
    public array $replacements = [];

    /**
     * @var list<string>
     */
    public array $removals = [];

    public function __construct(
        public readonly CustomerJournal $journal = new CustomerJournal,
    ) {}

    public function store(string $customerId, CustomerAddressSnapshot $address): self
    {
        $this->addresses[$customerId] = $address;

        return $this;
    }

    public function failingOnReplace(Throwable $failure): self
    {
        $this->replaceFailure = $failure;

        return $this;
    }

    public function forCustomer(string $customerId): ?CustomerAddressSnapshot
    {
        $this->journal->record('addresses.forCustomer');
        $this->reads[] = $customerId;

        return $this->addresses[$customerId] ?? null;
    }

    public function replaceForCustomer(string $customerId, CustomerAddressSnapshot $address): void
    {
        $this->journal->record('addresses.replace');
        $this->calls[] = ['customerId' => $customerId, 'address' => $address];

        if ($this->replaceFailure !== null) {
            throw $this->replaceFailure;
        }

        if (trim($address->street) === '' && ! isset($this->addresses[$customerId])) {
            return;
        }

        $this->addresses[$customerId] = $address;
        $this->replacements[] = ['customerId' => $customerId, 'address' => $address];
    }

    public function removeForCustomer(string $customerId): void
    {
        $this->journal->record('addresses.remove');
        $this->removals[] = $customerId;

        unset($this->addresses[$customerId]);
    }
}
