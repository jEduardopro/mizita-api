<?php

declare(strict_types=1);

namespace App\Domains\Availability\Application\Dtos;

use App\Domains\Availability\ValueObjects\ScheduleOwnerType;

final readonly class ApplyDefaultHoursInput
{
    public function __construct(
        public string $businessId,
        public ScheduleOwnerType $ownerType,
        public string $ownerId,
    ) {}
}
