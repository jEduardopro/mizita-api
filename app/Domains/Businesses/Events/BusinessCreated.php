<?php

declare(strict_types=1);

namespace App\Domains\Businesses\Events;

/**
 * Domain event: a plain readonly payload carrying identifiers, not entities.
 */
final readonly class BusinessCreated
{
    public function __construct(
        public string $id,
    ) {}
}
