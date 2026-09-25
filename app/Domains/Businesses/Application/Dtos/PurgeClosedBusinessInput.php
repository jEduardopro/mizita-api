<?php

declare(strict_types=1);

namespace App\Domains\Businesses\Application\Dtos;

final readonly class PurgeClosedBusinessInput
{
    public function __construct(
        public string $businessId,
    ) {}
}
