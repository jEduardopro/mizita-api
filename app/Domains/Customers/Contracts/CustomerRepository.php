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

    public function existsByEmail(string $businessId, CustomerEmail $email, ?string $exceptId = null): bool;

    /**
     * @param  list<string>  $customerIds
     */
    public function existsAmong(string $businessId, array $customerIds, ?string $exceptId = null): bool;

    /**
     * @throws CustomerEmailAlreadyTaken
     */
    public function save(Customer $customer): void;

    /**
     * @throws CustomerNotFound
     */
    public function delete(string $businessId, string $id): void;
}
