<?php

declare(strict_types=1);

namespace App\Domains\Appointments\Application\UseCases;

use App\Domains\Appointments\Application\Dtos\AppointmentData;
use App\Domains\Appointments\Application\Dtos\ListAppointmentsInput;
use App\Domains\Appointments\Application\Presenters\AppointmentPresenter;
use App\Domains\Appointments\Contracts\AppointmentRepository;
use App\Domains\Appointments\Contracts\CalendarAccess;
use App\Shared\Application\UseCaseResponse;
use App\Shared\Contracts\BusinessContext;
use App\Shared\Contracts\DomainFailure;

final class ListAppointments
{
    public function __construct(
        private readonly AppointmentRepository $appointments,
        private readonly AppointmentPresenter $presenter,
        private readonly BusinessContext $business,
        private readonly CalendarAccess $calendars,
    ) {}

    /**
     * @return UseCaseResponse<list<AppointmentData>>
     */
    public function handle(ListAppointmentsInput $input): UseCaseResponse
    {
        try {
            $input->validate();

            $businessId = $this->business->currentBusinessId();
            $scope = $this->calendars->scopeFor($businessId, $input->accountId);
            $booked = $this->appointments->search($businessId, $input->toRange(), $scope);

            return UseCaseResponse::success($this->presenter->describeMany($businessId, $booked));
        } catch (DomainFailure $failure) {
            return UseCaseResponse::failure($failure);
        }
    }
}
