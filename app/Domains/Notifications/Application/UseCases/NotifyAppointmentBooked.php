<?php

declare(strict_types=1);

namespace App\Domains\Notifications\Application\UseCases;

use App\Domains\Notifications\Application\Dtos\NotifyAppointmentBookedInput;
use App\Domains\Notifications\Contracts\BookedAppointments;
use App\Domains\Notifications\Contracts\StaffNotificationRepository;
use App\Domains\Notifications\Entities\StaffNotification;
use App\Domains\Notifications\Exceptions\NotifiedAppointmentNotFound;
use App\Domains\Notifications\ValueObjects\NotificationType;
use App\Shared\Application\UseCaseResponse;
use App\Shared\Contracts\Clock;
use App\Shared\Contracts\DomainFailure;
use App\Shared\Contracts\IdGenerator;

final class NotifyAppointmentBooked
{
    public function __construct(
        private readonly BookedAppointments $appointments,
        private readonly StaffNotificationRepository $notifications,
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

            $this->notifications->addOnce($this->notificationFor($input->appointmentId));
        } catch (DomainFailure $failure) {
            return UseCaseResponse::failure($failure);
        }

        return UseCaseResponse::success();
    }

    /**
     * @throws NotifiedAppointmentNotFound
     */
    private function notificationFor(string $appointmentId): StaffNotification
    {
        $appointment = $this->appointments->recipientOf($appointmentId);

        return StaffNotification::create(
            id: $this->ids->next(),
            businessId: $appointment->businessId,
            recipientStaffMemberId: $appointment->staffMemberId,
            type: NotificationType::AppointmentBooked,
            appointmentId: $appointment->appointmentId,
            now: $this->clock->now(),
        );
    }
}
