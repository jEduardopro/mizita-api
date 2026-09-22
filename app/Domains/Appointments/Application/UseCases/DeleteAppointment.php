<?php

declare(strict_types=1);

namespace App\Domains\Appointments\Application\UseCases;

use App\Domains\Appointments\Application\Dtos\DeleteAppointmentInput;
use App\Domains\Appointments\Contracts\AppointmentRepository;
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
    ) {}

    /**
     * @return UseCaseResponse<null>
     */
    public function handle(DeleteAppointmentInput $input): UseCaseResponse
    {
        try {
            $input->validate();

            $businessId = $this->business->currentBusinessId();

            $this->guardAgainstRecordedPayment($businessId, $input->appointmentId);

            $this->appointments->delete($businessId, $input->appointmentId);

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
