<?php

declare(strict_types=1);

namespace App\Domains\Businesses\Application\Dtos;

use App\Domains\Businesses\Exceptions\TooManyScheduleIntervals;
use App\Domains\Businesses\ValueObjects\BusinessScheduleEntry;

final readonly class ScheduleInput
{
    public const MAXIMUM_ENTRIES = self::DAYS_PER_WEEK * self::MAXIMUM_ENTRIES_PER_DAY;

    private const DAYS_PER_WEEK = 7;

    private const MAXIMUM_ENTRIES_PER_DAY = 10;

    /**
     * @param  list<BusinessScheduleEntry>  $entries
     */
    public function __construct(
        public array $entries,
    ) {}

    /**
     * @throws TooManyScheduleIntervals
     */
    public function validate(): void
    {
        $this->validateEntryCount();
    }

    /**
     * @throws TooManyScheduleIntervals
     */
    private function validateEntryCount(): void
    {
        if (count($this->entries) > self::MAXIMUM_ENTRIES) {
            throw TooManyScheduleIntervals::submitted(count($this->entries), self::MAXIMUM_ENTRIES);
        }
    }
}
