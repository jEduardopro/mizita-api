<?php

declare(strict_types=1);

namespace App\Domains\Availability\Events;

final readonly class StaffScheduleChanged
{
    public function __construct(
        public string $businessId,
        public string $staffMemberId,
    ) {}
}
