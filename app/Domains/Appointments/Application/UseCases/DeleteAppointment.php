<?php

declare(strict_types=1);

namespace App\Domains\Appointments\Application\UseCases;

use App\Domains\Appointments\Application\Dtos\DeleteAppointmentInput;
use App\Domains\Appointments\Contracts\AppointmentRepository;
use App\Domains\Appointments\Contracts\CalendarAccess;
use App\Domains\Appointments\Contracts\PaymentLedger;
use App\Domains\Appointments\Exceptions\AppointmentHasPayment;
use App\Shared\Application\UseCaseResponse;
use App\Shared\Contracts\BusinessContext;
use App\Shared\Contracts\DomainFailure;

final class DeleteAppointment
{
    public function __construct(
        private readonly AppointmentRepository $appointments,
        private readonly PaymentLedger $payments,
        private readonly BusinessContext $business,
        private readonly CalendarAccess $calendars,
    ) {}

    /**
     * @return UseCaseResponse<null>
     */
    public function handle(DeleteAppointmentInput $input): UseCaseResponse
    {
        try {
            $input->validate();

            $businessId = $this->business->currentBusinessId();
            $scope = $this->calendars->scopeFor($businessId, $input->accountId);
            $appointment = $this->appointments->findWithinScope($businessId, $input->appointmentId, $scope);

            $this->guardAgainstRecordedPayment($businessId, $appointment->id);

            $this->appointments->delete($businessId, $appointment->id);

            return UseCaseResponse::success();
        } catch (DomainFailure $failure) {
            return UseCaseResponse::failure($failure);
        }
    }

    /**
     * @throws AppointmentHasPayment
     */
    private function guardAgainstRecordedPayment(string $businessId, string $appointmentId): void
    {
        if ($this->payments->hasPaymentFor($businessId, $appointmentId)) {
            throw AppointmentHasPayment::withId($appointmentId);
        }
    }
}
