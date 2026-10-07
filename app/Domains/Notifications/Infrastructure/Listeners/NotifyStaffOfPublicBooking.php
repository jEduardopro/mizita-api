<?php

declare(strict_types=1);

namespace App\Domains\Notifications\Infrastructure\Listeners;

use App\Domains\Appointments\Events\AppointmentBooked;
use App\Domains\Notifications\Application\Dtos\NotifyAppointmentBookedInput;
use App\Domains\Notifications\Application\UseCases\NotifyAppointmentBooked;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;

final class NotifyStaffOfPublicBooking implements ShouldQueueAfterCommit
{
    private const BACKOFF_SECONDS = [30, 120];

    public int $tries = 3;

    public function __construct(
        private readonly NotifyAppointmentBooked $notifyAppointmentBooked,
    ) {}

    public function handle(AppointmentBooked $event): void
    {
        $this->notifyAppointmentBooked->handle(new NotifyAppointmentBookedInput($event->id))->value();
    }

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return self::BACKOFF_SECONDS;
    }
}
