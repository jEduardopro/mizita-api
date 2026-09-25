<?php

declare(strict_types=1);

namespace App\Domains\Integrations\Infrastructure\Queue;

use App\Domains\Integrations\Application\Dtos\ReconcileAppointmentCalendarEventsInput;
use App\Domains\Integrations\Application\UseCases\ReconcileAppointmentCalendarEvents;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;

final class SyncAppointmentToCalendar implements ShouldQueueAfterCommit
{
    use Queueable;

    private const BACKOFF_SECONDS = [30, 120, 600];

    private const RELEASE_WHILE_LOCKED_SECONDS = 10;

    private const LOCK_EXPIRES_AFTER_SECONDS = 180;

    public int $tries = 8;

    public function __construct(
        public readonly string $appointmentId,
    ) {}

    public function handle(ReconcileAppointmentCalendarEvents $reconcile): void
    {
        $reconcile->handle(new ReconcileAppointmentCalendarEventsInput($this->appointmentId));
    }

    /**
     * @return list<WithoutOverlapping>
     */
    public function middleware(): array
    {
        return [
            (new WithoutOverlapping($this->appointmentId))
                ->releaseAfter(self::RELEASE_WHILE_LOCKED_SECONDS)
                ->expireAfter(self::LOCK_EXPIRES_AFTER_SECONDS),
        ];
    }

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return self::BACKOFF_SECONDS;
    }
}
