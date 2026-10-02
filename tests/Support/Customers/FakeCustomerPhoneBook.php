<?php

declare(strict_types=1);

namespace Tests\Support\Customers;

use App\Domains\Customers\Contracts\CustomerPhoneBook;
use App\Domains\Phones\ValueObjects\PhoneNumberFragment;
use App\Shared\ValueObjects\PhoneNumber;
use Tests\Support\FakeBusinessContext;

final class FakeCustomerPhoneBook implements CustomerPhoneBook
{
    /**
     * @var array<string, PhoneNumber>
     */
    private array $numbers = [];

    /**
     * @var array<string, string>
     */
    private array $businessOf = [];

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

    /**
     * @var list<string>
     */
    public array $lookupBusinessIds = [];

    public function __construct(
        public readonly CustomerJournal $journal = new CustomerJournal,
    ) {}

    public function store(
        string $customerId,
        PhoneNumber $number,
        string $businessId = FakeBusinessContext::BUSINESS_ID,
    ): self {
        $this->numbers[$customerId] = $number;
        $this->businessOf[$customerId] = $businessId;

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
    public function customerIdsWithNumber(string $businessId, PhoneNumber $number): array
    {
        $this->journal->record('phones.idsWithNumber');
        $this->numberLookups[] = $number->e164();
        $this->lookupBusinessIds[] = $businessId;

        return $this->holders(
            $businessId,
            static fn (PhoneNumber $stored): bool => $stored->e164() === $number->e164(),
        );
    }

    /**
     * @return list<string>
     */
    public function customerIdsMatchingNumber(string $businessId, string $fragment): array
    {
        $this->journal->record('phones.idsMatchingNumber');
        $this->fragmentLookups[] = $fragment;
        $this->lookupBusinessIds[] = $businessId;

        $digits = PhoneNumberFragment::of($fragment)?->digits;

        if ($digits === null) {
            return [];
        }

        return $this->holders(
            $businessId,
            static fn (PhoneNumber $stored): bool => str_contains($stored->e164(), $digits),
        );
    }

    /**
     * @param  callable(PhoneNumber): bool  $matches
     * @return list<string>
     */
    private function holders(string $businessId, callable $matches): array
    {
        $holders = array_filter(
            $this->numbers,
            fn (PhoneNumber $stored, string $customerId): bool => ($this->businessOf[$customerId] ?? null) === $businessId
                && $matches($stored),
            ARRAY_FILTER_USE_BOTH,
        );

        return array_values(array_map(strval(...), array_keys($holders)));
    }
}
