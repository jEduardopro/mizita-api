<?php

declare(strict_types=1);

namespace App\Domains\Appointments\Application\UseCases;

use App\Domains\Appointments\Application\Dtos\DeleteAppointmentInput;
use App\Domains\Appointments\Contracts\AppointmentRepository;
use App\Shared\Application\UseCaseResponse;
use App\Shared\Contracts\BusinessContext;
use App\Shared\Contracts\DomainFailure;

final class DeleteAppointment
{
    public function __construct(
        private readonly AppointmentRepository $appointments,
        private readonly BusinessContext $business,
    ) {}

    /**
     * @return UseCaseResponse<null>
     */
    public function handle(DeleteAppointmentInput $input): UseCaseResponse
    {
        try {
            $input->validate();

            $this->appointments->delete(
                $this->business->currentBusinessId(),
                $input->appointmentId,
            );

            return UseCaseResponse::success();
        } catch (DomainFailure $failure) {
            return UseCaseResponse::failure($failure);
        }
    }
}
