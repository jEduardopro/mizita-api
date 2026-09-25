<?php

declare(strict_types=1);

namespace App\Domains\Integrations\Infrastructure\Queue;

use App\Domains\Integrations\Application\Dtos\ReleaseDisconnectedCalendarInput;
use App\Domains\Integrations\Application\UseCases\ReleaseDisconnectedCalendar;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Foundation\Queue\Queueable;

final class CleanUpDisconnectedCalendar implements ShouldQueueAfterCommit
{
    use Queueable;

    private const BACKOFF_SECONDS = [60, 600];

    public int $tries = 3;

    public function __construct(
        public readonly string $businessId,
        public readonly string $connectionId,
    ) {}

    public function handle(ReleaseDisconnectedCalendar $releaseCalendar): void
    {
        $releaseCalendar->handle(new ReleaseDisconnectedCalendarInput($this->businessId, $this->connectionId));
    }

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return self::BACKOFF_SECONDS;
    }
}
