<?php

declare(strict_types=1);

namespace App\Domains\Availability\Application\Dtos;

final readonly class ShowMyScheduleInput
{
    public function __construct(
        public string $accountId,
    ) {}
}
