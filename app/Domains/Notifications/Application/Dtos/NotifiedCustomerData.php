<?php

declare(strict_types=1);

namespace App\Domains\Notifications\Application\Dtos;

use App\Domains\Notifications\ValueObjects\NotifiedCustomer;

final readonly class NotifiedCustomerData
{
    public function __construct(
        public string $id,
        public string $name,
    ) {}

    public static function fromCustomer(NotifiedCustomer $customer): self
    {
        return new self(
            id: $customer->customerId,
            name: $customer->name,
        );
    }
}
