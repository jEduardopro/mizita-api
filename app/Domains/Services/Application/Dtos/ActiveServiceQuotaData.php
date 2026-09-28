<?php

declare(strict_types=1);

namespace App\Domains\Services\Application\Dtos;

final readonly class ActiveServiceQuotaData
{
    public function __construct(
        public int $activeCount,
        public ?int $activeLimit,
    ) {}
}
