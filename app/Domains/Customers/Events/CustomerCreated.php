<?php

declare(strict_types=1);

namespace App\Domains\Customers\Events;

final readonly class CustomerCreated
{
    public function __construct(
        public string $id,
    ) {}
}
