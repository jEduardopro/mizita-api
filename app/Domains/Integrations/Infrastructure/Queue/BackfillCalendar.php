<?php

declare(strict_types=1);

namespace App\Domains\Integrations\Infrastructure\Queue;

use App\Domains\Integrations\Application\Dtos\ScheduleCalendarBackfillInput;
use App\Domains\Integrations\Application\UseCases\ScheduleCalendarBackfill;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Foundation\Queue\Queueable;

final class BackfillCalendar implements ShouldQueueAfterCommit
{
    use Queueable;

    private const BACKOFF_SECONDS = [30, 120];

    public int $tries = 3;

    public function __construct(
        public readonly string $businessId,
        public readonly string $connectionId,
    ) {}

    public function handle(ScheduleCalendarBackfill $scheduleBackfill): void
    {
        $scheduleBackfill->handle(new ScheduleCalendarBackfillInput($this->businessId, $this->connectionId));
    }

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return self::BACKOFF_SECONDS;
    }
}
