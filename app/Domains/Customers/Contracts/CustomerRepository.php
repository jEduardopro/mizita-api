<?php

declare(strict_types=1);

namespace App\Domains\Customers\Contracts;

use App\Domains\Customers\Entities\Customer;
use App\Domains\Customers\Exceptions\CustomerNotFound;

interface CustomerRepository
{
    /**
     * @throws CustomerNotFound
     */
    public function findById(string $id): Customer;

    public function save(Customer $customer): void;

    /**
     * @throws CustomerNotFound
     */
    public function delete(string $id): void;
}
