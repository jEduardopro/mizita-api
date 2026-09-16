<?php

declare(strict_types=1);

namespace App\Domains\Availability\Application\Dtos;

use App\Domains\Availability\ValueObjects\ScheduleInterval;
use App\Domains\Availability\ValueObjects\ScheduleOwnerType;

final readonly class ReplaceScheduleInput
{
    /**
     * @param  list<ScheduleInterval>  $intervals
     */
    public function __construct(
        public ScheduleOwnerType $ownerType,
        public string $ownerId,
        public array $intervals,
    ) {}
}
