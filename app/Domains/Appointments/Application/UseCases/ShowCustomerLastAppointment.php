<?php

declare(strict_types=1);

namespace App\Domains\Appointments\Application\UseCases;

use App\Domains\Appointments\Application\Dtos\AppointmentData;
use App\Domains\Appointments\Application\Dtos\ShowCustomerLastAppointmentInput;
use App\Domains\Appointments\Application\Presenters\AppointmentPresenter;
use App\Domains\Appointments\Contracts\AppointmentRepository;
use App\Domains\Appointments\Contracts\CalendarAccess;
use App\Domains\Appointments\Contracts\CustomerDirectory;
use App\Shared\Application\UseCaseResponse;
use App\Shared\Contracts\BusinessContext;
use App\Shared\Contracts\Clock;
use App\Shared\Contracts\DomainFailure;

final class ShowCustomerLastAppointment
{
    public function __construct(
        private readonly AppointmentRepository $appointments,
        private readonly CustomerDirectory $customers,
        private readonly AppointmentPresenter $presenter,
        private readonly BusinessContext $business,
        private readonly CalendarAccess $calendars,
        private readonly Clock $clock,
    ) {}

    /**
     * @return UseCaseResponse<AppointmentData|null>
     */
    public function handle(ShowCustomerLastAppointmentInput $input): UseCaseResponse
    {
        try {
            $input->validate();

            $businessId = $this->business->currentBusinessId();

            $this->customers->describe($businessId, $input->customerId);

            $scope = $this->calendars->scopeFor($businessId, $input->accountId);
            $last = $this->appointments->lastAttendedForCustomer(
                $businessId,
                $input->customerId,
                $scope,
                $this->clock->now(),
            );

            if ($last === null) {
                return UseCaseResponse::success(null);
            }

            return UseCaseResponse::success($this->presenter->describe($businessId, $last));
        } catch (DomainFailure $failure) {
            return UseCaseResponse::failure($failure);
        }
    }
}
