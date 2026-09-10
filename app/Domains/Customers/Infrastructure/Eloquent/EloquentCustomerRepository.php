<?php

declare(strict_types=1);

namespace App\Domains\Customers\Infrastructure\Eloquent;

use App\Domains\Customers\Contracts\CustomerRepository;
use App\Domains\Customers\Entities\Customer;
use App\Domains\Customers\Exceptions\CustomerNotFound;
use App\Domains\Customers\Infrastructure\Eloquent\Mappers\CustomerMapper;
use App\Domains\Customers\Infrastructure\Eloquent\Models\CustomerModel;

final class EloquentCustomerRepository implements CustomerRepository
{
    public function __construct(
        private readonly CustomerMapper $mapper,
    ) {}

    public function findById(string $id): Customer
    {
        return $this->mapper->toEntity($this->modelOrFail($id));
    }

    public function save(Customer $customer): void
    {
        CustomerModel::query()->updateOrCreate(
            ['uuid' => $customer->id],
            $this->mapper->toAttributes($customer),
        );
    }

    public function delete(string $id): void
    {
        $this->modelOrFail($id)->delete();
    }

    private function modelOrFail(string $id): CustomerModel
    {
        $model = CustomerModel::query()->where('uuid', $id)->first();

        if ($model === null) {
            throw CustomerNotFound::withId($id);
        }

        return $model;
    }
}
