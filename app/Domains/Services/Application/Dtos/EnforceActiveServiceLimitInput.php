<?php

declare(strict_types=1);

namespace App\Domains\Services\Application\Dtos;

final readonly class EnforceActiveServiceLimitInput
{
    public function __construct(
        public string $businessId,
    ) {}
}
