<?php

declare(strict_types=1);

namespace Tests\Support\Customers;

use App\Domains\Customers\Contracts\CustomerPhoneBook;
use App\Shared\ValueObjects\PhoneNumber;

final class FakeCustomerPhoneBook implements CustomerPhoneBook
{
    private const NON_DIGITS = '/\D+/u';

    /**
     * @var array<string, PhoneNumber>
     */
    private array $numbers = [];

    /**
     * @var list<string>
     */
    public array $reads = [];

    /**
     * @var list<list<string>>
     */
    public array $batchReads = [];

    /**
     * @var list<array{customerId: string, phone: ?PhoneNumber}>
     */
    public array $replacements = [];

    /**
     * @var list<string>
     */
    public array $removals = [];

    /**
     * @var list<string>
     */
    public array $numberLookups = [];

    /**
     * @var list<string>
     */
    public array $fragmentLookups = [];

    public function __construct(
        public readonly CustomerJournal $journal = new CustomerJournal,
    ) {}

    public function store(string $customerId, PhoneNumber $number): self
    {
        $this->numbers[$customerId] = $number;

        return $this;
    }

    public function forCustomer(string $customerId): ?PhoneNumber
    {
        $this->journal->record('phones.forCustomer');
        $this->reads[] = $customerId;

        return $this->numbers[$customerId] ?? null;
    }

    /**
     * @param  list<string>  $customerIds
     * @return array<string, PhoneNumber>
     */
    public function forCustomers(array $customerIds): array
    {
        $this->journal->record('phones.forCustomers');
        $this->batchReads[] = $customerIds;

        $found = [];

        foreach ($customerIds as $customerId) {
            if (isset($this->numbers[$customerId])) {
                $found[$customerId] = $this->numbers[$customerId];
            }
        }

        return $found;
    }

    public function replaceForCustomer(string $customerId, ?PhoneNumber $phone): void
    {
        $this->journal->record('phones.replace');
        $this->replacements[] = ['customerId' => $customerId, 'phone' => $phone];

        if ($phone === null) {
            unset($this->numbers[$customerId]);

            return;
        }

        $this->numbers[$customerId] = $phone;
    }

    public function removeForCustomer(string $customerId): void
    {
        $this->journal->record('phones.remove');
        $this->removals[] = $customerId;

        unset($this->numbers[$customerId]);
    }

    /**
     * @return list<string>
     */
    public function customerIdsWithNumber(PhoneNumber $number): array
    {
        $this->journal->record('phones.idsWithNumber');
        $this->numberLookups[] = $number->e164();

        return $this->holders(
            static fn (PhoneNumber $stored): bool => $stored->e164() === $number->e164(),
        );
    }

    /**
     * @return list<string>
     */
    public function customerIdsMatchingNumber(string $fragment): array
    {
        $this->journal->record('phones.idsMatchingNumber');
        $this->fragmentLookups[] = $fragment;

        $digits = (string) preg_replace(self::NON_DIGITS, '', $fragment);

        if ($digits === '') {
            return [];
        }

        return $this->holders(
            static fn (PhoneNumber $stored): bool => str_contains($stored->e164(), $digits),
        );
    }

    /**
     * @param  callable(PhoneNumber): bool  $matches
     * @return list<string>
     */
    private function holders(callable $matches): array
    {
        return array_values(array_keys(array_filter($this->numbers, $matches)));
    }
}
