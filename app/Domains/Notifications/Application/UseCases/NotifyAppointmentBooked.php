<?php

declare(strict_types=1);

namespace App\Domains\Notifications\Application\UseCases;

use App\Domains\Notifications\Application\Dtos\NotifyAppointmentBookedInput;
use App\Domains\Notifications\Contracts\BookedAppointments;
use App\Domains\Notifications\Contracts\StaffNotificationRepository;
use App\Domains\Notifications\Entities\NotificationEvent;
use App\Domains\Notifications\ValueObjects\BookedAppointment;
use App\Shared\Application\UseCaseResponse;
use App\Shared\Contracts\Clock;
use App\Shared\Contracts\DomainFailure;
use App\Shared\Contracts\IdGenerator;
use App\Shared\Contracts\TransactionManager;

final class NotifyAppointmentBooked
{
    public function __construct(
        private readonly BookedAppointments $appointments,
        private readonly StaffNotificationRepository $notifications,
        private readonly TransactionManager $transactions,
        private readonly IdGenerator $ids,
        private readonly Clock $clock,
    ) {}

    /**
     * @return UseCaseResponse<null>
     */
    public function handle(NotifyAppointmentBookedInput $input): UseCaseResponse
    {
        try {
            $input->validate();

            $this->notifyStaffMemberOf($this->appointments->describe($input->appointmentId));
        } catch (DomainFailure $failure) {
            return UseCaseResponse::failure($failure);
        }

        return UseCaseResponse::success();
    }

    private function notifyStaffMemberOf(BookedAppointment $appointment): void
    {
        $event = NotificationEvent::record(
            id: $this->ids->next(),
            businessId: $appointment->businessId,
            payload: $appointment->payload(),
            occurredAt: $this->clock->now(),
        );

        $delivery = $event->deliverTo($this->ids->next(), $appointment->staffMemberId);

        $this->transactions->run(fn () => $this->notifications->record($event, $delivery));
    }
}
