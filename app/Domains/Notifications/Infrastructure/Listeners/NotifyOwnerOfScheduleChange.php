<?php

declare(strict_types=1);

namespace App\Domains\Notifications\Infrastructure\Listeners;

use App\Domains\Availability\Events\StaffScheduleChanged;
use App\Domains\Notifications\Application\Dtos\NotifyStaffScheduleChangedInput;
use App\Domains\Notifications\Application\UseCases\NotifyStaffScheduleChanged;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;

final class NotifyOwnerOfScheduleChange implements ShouldQueueAfterCommit
{
    private const BACKOFF_SECONDS = [30, 120];

    public int $tries = 3;

    public function __construct(
        private readonly NotifyStaffScheduleChanged $notifyStaffScheduleChanged,
    ) {}

    public function handle(StaffScheduleChanged $event): void
    {
        $this->notifyStaffScheduleChanged
            ->handle(new NotifyStaffScheduleChangedInput($event->businessId, $event->staffMemberId))
            ->value();
    }

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return self::BACKOFF_SECONDS;
    }
}
