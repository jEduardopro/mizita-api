<?php

declare(strict_types=1);

namespace App\Domains\Appointments\Application\UseCases;

use App\Domains\Appointments\Application\Dtos\AppointmentData;
use App\Domains\Appointments\Application\Dtos\CancelAppointmentInput;
use App\Domains\Appointments\Application\Presenters\AppointmentPresenter;
use App\Domains\Appointments\Contracts\AppointmentRepository;
use App\Domains\Appointments\ValueObjects\Canceller;
use App\Shared\Application\UseCaseResponse;
use App\Shared\Contracts\BusinessContext;
use App\Shared\Contracts\Clock;
use App\Shared\Contracts\DomainFailure;

final class CancelAppointment
{
    public function __construct(
        private readonly AppointmentRepository $appointments,
        private readonly AppointmentPresenter $presenter,
        private readonly BusinessContext $business,
        private readonly Clock $clock,
    ) {}

    /**
     * @return UseCaseResponse<AppointmentData>
     */
    public function handle(CancelAppointmentInput $input): UseCaseResponse
    {
        try {
            $input->validate();

            $businessId = $this->business->currentBusinessId();
            $appointment = $this->appointments->findForBusiness($businessId, $input->appointmentId);

            $appointment->cancel(Canceller::Business, $this->clock->now());

            $this->appointments->save($appointment);

            return UseCaseResponse::success($this->presenter->describe($businessId, $appointment));
        } catch (DomainFailure $failure) {
            return UseCaseResponse::failure($failure);
        }
    }
}
