<?php

declare(strict_types=1);

namespace App\Domains\Integrations\Application\Dtos;

final readonly class ScheduleCalendarBackfillInput
{
    public function __construct(
        public string $businessId,
        public string $connectionId,
    ) {}
}
