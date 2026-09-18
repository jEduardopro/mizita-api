<?php

declare(strict_types=1);

namespace App\Domains\Appointments\Application\UseCases;

use App\Domains\Appointments\Application\Dtos\AppointmentData;
use App\Domains\Appointments\Application\Dtos\UpdateAppointmentInput;
use App\Domains\Appointments\Application\Presenters\AppointmentPresenter;
use App\Domains\Appointments\Contracts\AppointmentRepository;
use App\Domains\Appointments\Contracts\CustomerDirectory;
use App\Domains\Appointments\Contracts\ServiceCatalog;
use App\Domains\Appointments\Contracts\StaffDirectory;
use App\Domains\Appointments\Entities\Appointment;
use App\Domains\Appointments\Exceptions\AppointmentCustomerNotFound;
use App\Domains\Appointments\Exceptions\AppointmentOverlaps;
use App\Domains\Appointments\Exceptions\AppointmentServiceNotFound;
use App\Domains\Appointments\Exceptions\AppointmentStaffNotFound;
use App\Domains\Appointments\ValueObjects\AppointmentSlot;
use App\Domains\Appointments\ValueObjects\ServiceSnapshot;
use App\Shared\Application\UseCaseResponse;
use App\Shared\Contracts\BusinessContext;
use App\Shared\Contracts\DomainFailure;
use DateTimeImmutable;

final class UpdateAppointment
{
    public function __construct(
        private readonly AppointmentRepository $appointments,
        private readonly ServiceCatalog $services,
        private readonly CustomerDirectory $customers,
        private readonly StaffDirectory $staff,
        private readonly AppointmentPresenter $presenter,
        private readonly BusinessContext $business,
    ) {}

    /**
     * @return UseCaseResponse<AppointmentData>
     */
    public function handle(UpdateAppointmentInput $input): UseCaseResponse
    {
        try {
            $input->validate();

            $businessId = $this->business->currentBusinessId();
            $appointment = $this->appointments->findForBusiness($businessId, $input->appointmentId);

            $this->apply($input, $appointment, $businessId);

            return UseCaseResponse::success($this->presenter->describe($businessId, $appointment));
        } catch (DomainFailure $failure) {
            return UseCaseResponse::failure($failure);
        }
    }

    /**
     * @throws AppointmentCustomerNotFound
     * @throws AppointmentServiceNotFound
     * @throws AppointmentStaffNotFound
     * @throws AppointmentOverlaps
     */
    private function apply(UpdateAppointmentInput $input, Appointment $appointment, string $businessId): void
    {
        $service = $this->services->describe($businessId, $input->serviceId);

        $this->customers->describe($businessId, $input->customerId);
        $this->staff->describe($businessId, $input->staffMemberId);

        $appointment->changeCustomer($input->customerId);
        $appointment->changeService($input->serviceId);
        $appointment->reassign($input->staffMemberId);
        $appointment->reschedule(self::slotFor($input->toStartsAt(), $input->toEndsAt(), $service));
        $appointment->changeNotes($input->toNotes());

        $this->appointments->save($appointment);
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
