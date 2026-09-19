<?php

declare(strict_types=1);

namespace App\Domains\Appointments\Application\UseCases;

use App\Domains\Appointments\Application\Dtos\GuestBookingData;
use App\Domains\Appointments\Application\Dtos\RescheduleGuestBookingInput;
use App\Domains\Appointments\Application\Presenters\GuestBookingPresenter;
use App\Domains\Appointments\Application\Services\GuestBookingFinder;
use App\Domains\Appointments\Contracts\AppointmentRepository;
use App\Domains\Appointments\Contracts\BookableSlots;
use App\Domains\Appointments\Contracts\CancellationPolicy;
use App\Domains\Appointments\Contracts\OpeningHours;
use App\Domains\Appointments\Contracts\ServiceCatalog;
use App\Domains\Appointments\Entities\Appointment;
use App\Domains\Appointments\Exceptions\AppointmentSlotNotBookable;
use App\Domains\Appointments\Exceptions\BusinessCurrentlyClosed;
use App\Domains\Appointments\Services\AppointmentChangeWindow;
use App\Domains\Appointments\ValueObjects\AppointmentSlot;
use App\Domains\Appointments\ValueObjects\ManageTokenExpiry;
use App\Shared\Application\UseCaseResponse;
use App\Shared\Contracts\Clock;
use App\Shared\Contracts\DomainFailure;
use DateTimeImmutable;

final class RescheduleGuestBooking
{
    public function __construct(
        private readonly AppointmentRepository $appointments,
        private readonly GuestBookingFinder $bookings,
        private readonly ServiceCatalog $services,
        private readonly BookableSlots $slots,
        private readonly OpeningHours $openingHours,
        private readonly CancellationPolicy $policies,
        private readonly AppointmentChangeWindow $changeWindow,
        private readonly GuestBookingPresenter $presenter,
        private readonly Clock $clock,
    ) {}

    /**
     * @return UseCaseResponse<GuestBookingData>
     */
    public function handle(RescheduleGuestBookingInput $input): UseCaseResponse
    {
        try {
            $input->validate();

            $this->refuseWhileClosed($input->businessId);

            $now = $this->clock->now();
            $appointment = $this->bookings->find($input->businessId, $input->credentials, $now);
            $rule = $this->policies->forBusiness($input->businessId);

            $this->changeWindow->ensureOpenFor($appointment, $rule, $now);

            $slot = $this->bookableSlotFor($input->businessId, $appointment, $input->toStartsAt());

            $appointment->rescheduleAsGuest($slot, ManageTokenExpiry::forSlot($slot), $now);

            $this->appointments->save($appointment);

            return UseCaseResponse::success(
                $this->presenter->describe($input->businessId, $appointment, $rule, $now),
            );
        } catch (DomainFailure $failure) {
            return UseCaseResponse::failure($failure);
        }
    }

    /**
     * @throws BusinessCurrentlyClosed
     */
    private function refuseWhileClosed(string $businessId): void
    {
        if (! $this->openingHours->isOpenNow($businessId)) {
            throw BusinessCurrentlyClosed::forBusiness($businessId);
        }
    }

    /**
     * @throws AppointmentSlotNotBookable
     */
    private function bookableSlotFor(
        string $businessId,
        Appointment $appointment,
        DateTimeImmutable $startsAt,
    ): AppointmentSlot {
        $bookable = $this->slots->isBookable(
            $businessId,
            $appointment->serviceId(),
            $appointment->staffMemberId(),
            $startsAt,
            excludingAppointmentId: $appointment->id,
        );

        if (! $bookable) {
            throw AppointmentSlotNotBookable::startingAt($startsAt);
        }

        return AppointmentSlot::lasting(
            $startsAt,
            $this->services->describe($businessId, $appointment->serviceId())->durationMinutes,
        );
    }
}
