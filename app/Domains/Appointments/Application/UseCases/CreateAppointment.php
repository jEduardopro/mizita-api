<?php

declare(strict_types=1);

namespace App\Domains\Appointments\Application\UseCases;

use App\Domains\Appointments\Application\Dtos\AppointmentData;
use App\Domains\Appointments\Application\Dtos\CreateAppointmentInput;
use App\Domains\Appointments\Application\Presenters\AppointmentPresenter;
use App\Domains\Appointments\Contracts\AppointmentRepository;
use App\Domains\Appointments\Contracts\CustomerDirectory;
use App\Domains\Appointments\Contracts\ServiceCatalog;
use App\Domains\Appointments\Contracts\StaffDirectory;
use App\Domains\Appointments\Entities\Appointment;
use App\Domains\Appointments\Events\AppointmentCreated;
use App\Domains\Appointments\Exceptions\AppointmentCustomerNotFound;
use App\Domains\Appointments\Exceptions\AppointmentOverlaps;
use App\Domains\Appointments\Exceptions\AppointmentServiceNotFound;
use App\Domains\Appointments\Exceptions\AppointmentStaffNotFound;
use App\Domains\Appointments\ValueObjects\AppointmentSlot;
use App\Domains\Appointments\ValueObjects\ServiceSnapshot;
use App\Shared\Application\UseCaseResponse;
use App\Shared\Contracts\BusinessContext;
use App\Shared\Contracts\Clock;
use App\Shared\Contracts\DomainFailure;
use App\Shared\Contracts\IdGenerator;
use DateTimeImmutable;
use Illuminate\Contracts\Events\Dispatcher;

final class CreateAppointment
{
    public function __construct(
        private readonly AppointmentRepository $appointments,
        private readonly ServiceCatalog $services,
        private readonly CustomerDirectory $customers,
        private readonly StaffDirectory $staff,
        private readonly AppointmentPresenter $presenter,
        private readonly IdGenerator $ids,
        private readonly Clock $clock,
        private readonly BusinessContext $business,
        private readonly Dispatcher $events,
    ) {}

    /**
     * @return UseCaseResponse<AppointmentData>
     */
    public function handle(CreateAppointmentInput $input): UseCaseResponse
    {
        try {
            $input->validate();

            $businessId = $this->business->currentBusinessId();
            $appointment = $this->book($input, $businessId);
            $booked = $this->presenter->describe($businessId, $appointment);
        } catch (DomainFailure $failure) {
            return UseCaseResponse::failure($failure);
        }

        $this->events->dispatch(new AppointmentCreated($appointment->id));

        return UseCaseResponse::success($booked);
    }

    /**
     * @throws AppointmentServiceNotFound
     * @throws AppointmentCustomerNotFound
     * @throws AppointmentStaffNotFound
     * @throws AppointmentOverlaps
     */
    private function book(CreateAppointmentInput $input, string $businessId): Appointment
    {
        $service = $this->services->describe($businessId, $input->serviceId);

        $this->ensureParticipantsAreKnown($businessId, $input->customerId, $input->staffMemberId);

        $appointment = Appointment::create(
            id: $this->ids->next(),
            businessId: $businessId,
            customerId: $input->customerId,
            serviceId: $input->serviceId,
            staffMemberId: $input->staffMemberId,
            slot: self::slotFor($input->toStartsAt(), $input->toEndsAt(), $service),
            notes: $input->toNotes(),
            now: $this->clock->now(),
        );

        $this->appointments->save($appointment);

        return $appointment;
    }

    /**
     * @throws AppointmentCustomerNotFound
     * @throws AppointmentStaffNotFound
     */
    private function ensureParticipantsAreKnown(string $businessId, string $customerId, string $staffMemberId): void
    {
        $this->customers->describe($businessId, $customerId);
        $this->staff->describe($businessId, $staffMemberId);
    }

    private static function slotFor(
        DateTimeImmutable $startsAt,
        ?DateTimeImmutable $endsAt,
        ServiceSnapshot $service,
    ): AppointmentSlot {
        if ($endsAt === null) {
            return AppointmentSlot::lasting($startsAt, $service->durationMinutes);
        }

        return AppointmentSlot::between($startsAt, $endsAt);
    }
}
