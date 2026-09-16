<?php

declare(strict_types=1);

namespace App\Domains\Businesses\Application\Dtos;

use App\Domains\Businesses\ValueObjects\BusinessScheduleEntry;

final readonly class ScheduleInput
{
    /**
     * @param  list<BusinessScheduleEntry>  $entries
     */
    public function __construct(
        public array $entries,
    ) {}
}
