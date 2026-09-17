<?php

declare(strict_types=1);

namespace App\Domains\Customers\Infrastructure\Eloquent\Mappers;

use App\Domains\Customers\Entities\Customer;
use App\Domains\Customers\Infrastructure\Eloquent\Models\CustomerModel;
use App\Domains\Customers\ValueObjects\BirthDate;
use App\Domains\Customers\ValueObjects\CustomerEmail;
use DateTimeImmutable;

final class CustomerMapper
{
    public function toEntity(CustomerModel $model, string $businessId): Customer
    {
        return Customer::restore(
            id: $model->uuid,
            businessId: $businessId,
            name: $model->name,
            email: $model->email === null ? null : CustomerEmail::restore($model->email),
            birthDate: $model->birth_date === null
                ? null
                : DateTimeImmutable::createFromInterface($model->birth_date),
            notes: $model->notes,
            createdAt: DateTimeImmutable::createFromInterface($model->created_at),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toAttributes(Customer $customer, int $businessKey): array
    {
        return [
            'uuid' => $customer->id,
            'business_id' => $businessKey,
            'name' => $customer->name(),
            'email' => $customer->email()?->value,
            'birth_date' => $customer->birthDate()?->format(BirthDate::FORMAT),
            'notes' => $customer->notes(),
        ];
    }
}
