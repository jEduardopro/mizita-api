<?php

declare(strict_types=1);

namespace App\Domains\Customers\Contracts;

use App\Domains\Customers\Entities\Customer;
use App\Domains\Customers\Exceptions\CustomerEmailAlreadyTaken;
use App\Domains\Customers\Exceptions\CustomerNotFound;
use App\Domains\Customers\ValueObjects\CustomerEmail;
use App\Domains\Customers\ValueObjects\CustomerQuery;
use App\Shared\ValueObjects\Paginated;

interface CustomerRepository
{
    /**
     * @return Paginated<Customer>
     */
    public function search(string $businessId, CustomerQuery $query): Paginated;

    /**
     * @throws CustomerNotFound
     */
    public function findForBusiness(string $businessId, string $id): Customer;

    /**
     * @throws CustomerNotFound
     */
    public function findIncludingArchived(string $businessId, string $id): Customer;

    /**
     * @param  list<string>  $ids
     * @return list<Customer>
     */
    public function findManyIncludingArchived(string $businessId, array $ids): array;

    public function existsByEmail(string $businessId, CustomerEmail $email, ?string $exceptId = null): bool;

    /**
     * @param  list<string>  $customerIds
     */
    public function existsAmong(string $businessId, array $customerIds, ?string $exceptId = null): bool;

    public function findByEmail(string $businessId, CustomerEmail $email): ?Customer;

    /**
     * @param  list<string>  $customerIds
     */
    public function findFirstAmong(string $businessId, array $customerIds): ?Customer;

    /**
     * @throws CustomerEmailAlreadyTaken
     */
    public function save(Customer $customer): void;

    /**
     * @throws CustomerNotFound
     */
    public function delete(string $businessId, string $id): void;
}
