<?php

declare(strict_types=1);

namespace App\Domains\Businesses\Events;

final readonly class BusinessCreated
{
    public function __construct(
        public string $id,
    ) {}
}
