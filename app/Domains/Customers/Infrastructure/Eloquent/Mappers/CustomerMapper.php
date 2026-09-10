<?php

declare(strict_types=1);

namespace App\Domains\Customers\Infrastructure\Eloquent\Mappers;

use App\Domains\Customers\Entities\Customer;
use App\Domains\Customers\Infrastructure\Eloquent\Models\CustomerModel;
use DateTimeImmutable;

/**
 * Translates between the persistence model and the domain entity. Only the
 * repository adapter uses it.
 */
final class CustomerMapper
{
    public function toEntity(CustomerModel $model): Customer
    {
        return Customer::restore(
            // The uuid is the domain identity; the int primary key stays here.
            id: $model->uuid,
            businessId: $model->business_id,
            name: $model->name,
            email: $model->email,
            phone: $model->phone,
            createdAt: DateTimeImmutable::createFromInterface($model->created_at),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toAttributes(Customer $customer): array
    {
        return [
            'uuid' => $customer->id,
            'business_id' => $customer->businessId,
            'name' => $customer->name(),
            'email' => $customer->email(),
            'phone' => $customer->phone(),
        ];
    }
}
