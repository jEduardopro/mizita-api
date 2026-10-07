<?php

declare(strict_types=1);

namespace App\Domains\Notifications\ValueObjects;

final readonly class NotifiedCustomer
{
    public function __construct(
        public string $customerId,
        public string $name,
    ) {}
}
