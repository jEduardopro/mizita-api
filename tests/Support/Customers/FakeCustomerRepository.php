<?php

declare(strict_types=1);

namespace Tests\Support\Customers;

use App\Domains\Customers\Contracts\CustomerRepository;
use App\Domains\Customers\Entities\Customer;
use App\Domains\Customers\Exceptions\CustomerNotFound;
use App\Domains\Customers\ValueObjects\CustomerEmail;
use App\Domains\Customers\ValueObjects\CustomerQuery;
use App\Shared\ValueObjects\Paginated;
use Throwable;

final class FakeCustomerRepository implements CustomerRepository
{
    /**
     * @var array<string, Customer>
     */
    private array $customers = [];

    /**
     * @var Paginated<Customer>|null
     */
    private ?Paginated $page = null;

    private ?Throwable $saveFailure = null;

    /**
     * @var list<Customer>
     */
    public array $saved = [];

    /**
     * @var list<array{businessId: string, id: string}>
     */
    public array $deleted = [];

    /**
     * @var list<string>
     */
    public array $businessIdsSeen = [];

    /**
     * @var list<CustomerQuery>
     */
    public array $queries = [];

    /**
     * @var list<array{businessId: string, email: string, exceptId: ?string}>
     */
    public array $emailChecks = [];

    /**
     * @var list<array{businessId: string, customerIds: list<string>, exceptId: ?string}>
     */
    public array $membershipChecks = [];

    public function __construct(
        public readonly CustomerJournal $journal = new CustomerJournal,
    ) {}

    public function store(Customer ...$customers): self
    {
        foreach ($customers as $customer) {
            $this->customers[$this->keyFor($customer->businessId, $customer->id)] = $customer;
        }

        return $this;
    }

    /**
     * @param  Paginated<Customer>  $page
     */
    public function returning(Paginated $page): self
    {
        $this->page = $page;

        return $this;
    }

    public function failingOnSave(Throwable $failure): self
    {
        $this->saveFailure = $failure;

        return $this;
    }

    /**
     * @return Paginated<Customer>
     */
    public function search(string $businessId, CustomerQuery $query): Paginated
    {
        $this->journal->record('customers.search');
        $this->businessIdsSeen[] = $businessId;
        $this->queries[] = $query;

        return $this->page ?? Paginated::of([], 0, $query->pagination);
    }

    public function findForBusiness(string $businessId, string $id): Customer
    {
        $this->journal->record('customers.find');
        $this->businessIdsSeen[] = $businessId;

        return $this->customers[$this->keyFor($businessId, $id)]
            ?? throw CustomerNotFound::withId($id);
    }

    public function existsByEmail(string $businessId, CustomerEmail $email, ?string $exceptId = null): bool
    {
        $this->journal->record('customers.existsByEmail');
        $this->businessIdsSeen[] = $businessId;
        $this->emailChecks[] = [
            'businessId' => $businessId,
            'email' => $email->value,
            'exceptId' => $exceptId,
        ];

        foreach ($this->customers as $customer) {
            if ($customer->businessId !== $businessId || $customer->id === $exceptId) {
                continue;
            }

            if (mb_strtolower((string) $customer->email()?->value) === mb_strtolower($email->value)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<string>  $customerIds
     */
    public function existsAmong(string $businessId, array $customerIds, ?string $exceptId = null): bool
    {
        $this->journal->record('customers.existsAmong');
        $this->businessIdsSeen[] = $businessId;
        $this->membershipChecks[] = [
            'businessId' => $businessId,
            'customerIds' => $customerIds,
            'exceptId' => $exceptId,
        ];

        foreach ($customerIds as $customerId) {
            if ($customerId === $exceptId) {
                continue;
            }

            if (isset($this->customers[$this->keyFor($businessId, $customerId)])) {
                return true;
            }
        }

        return false;
    }

    public function save(Customer $customer): void
    {
        $this->journal->record('customers.save');

        if ($this->saveFailure !== null) {
            throw $this->saveFailure;
        }

        $this->customers[$this->keyFor($customer->businessId, $customer->id)] = $customer;
        $this->saved[] = $customer;
    }

    public function delete(string $businessId, string $id): void
    {
        $this->journal->record('customers.delete');
        $this->businessIdsSeen[] = $businessId;
        $key = $this->keyFor($businessId, $id);

        if (! isset($this->customers[$key])) {
            throw CustomerNotFound::withId($id);
        }

        unset($this->customers[$key]);

        $this->deleted[] = ['businessId' => $businessId, 'id' => $id];
    }

    private function keyFor(string $businessId, string $id): string
    {
        return $businessId.'|'.$id;
    }
}
