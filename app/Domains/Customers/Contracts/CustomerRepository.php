<?php

declare(strict_types=1);

namespace App\Domains\Customers\Contracts;

use App\Domains\Customers\Entities\Customer;
use App\Domains\Customers\Exceptions\CustomerNotFound;

/**
 * Port for Customer persistence. It speaks entities, never Eloquent models
 * or query builders, so use cases stay independent of the database.
 */
interface CustomerRepository
{
    /**
     * @throws CustomerNotFound
     */
    public function findById(string $id): Customer;

    public function save(Customer $customer): void;

    /**
     * Soft deletes the record. Reads stop returning it; the row is kept.
     *
     * @throws CustomerNotFound
     */
    public function delete(string $id): void;
}
