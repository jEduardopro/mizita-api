<?php

declare(strict_types=1);

namespace App\Domains\Services\Events;

final readonly class ServiceCreated
{
    public function __construct(
        public string $id,
    ) {}
}
