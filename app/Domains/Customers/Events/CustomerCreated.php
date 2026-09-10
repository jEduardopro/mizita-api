<?php

declare(strict_types=1);

namespace App\Domains\Customers\Events;

/**
 * Domain event: a plain readonly payload carrying identifiers, not entities.
 */
final readonly class CustomerCreated
{
    public function __construct(
        public string $id,
    ) {}
}
