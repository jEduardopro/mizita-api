<?php

declare(strict_types=1);

namespace App\Domains\Customers\Application\Dtos;

/**
 * Input boundary for CreateCustomer. Framework free: the controller maps
 * the HTTP request into this object.
 */
final readonly class CreateCustomerInput
{
    public function __construct(
        public string $name,
        public ?string $email,
        public ?string $phone,
    ) {}
}
