<?php

declare(strict_types=1);

namespace App\Domains\Appointments\Application\UseCases;

use App\Domains\Appointments\Application\Dtos\AppointmentData;
use App\Domains\Appointments\Application\Dtos\ListCustomerAppointmentsInput;
use App\Domains\Appointments\Application\Presenters\AppointmentPresenter;
use App\Domains\Appointments\Contracts\AppointmentRepository;
use App\Domains\Appointments\Contracts\CalendarAccess;
use App\Domains\Appointments\Contracts\CustomerDirectory;
use App\Shared\Application\UseCaseResponse;
use App\Shared\Contracts\BusinessContext;
use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\Paginated;

final class ListCustomerAppointments
{
    public function __construct(
        private readonly AppointmentRepository $appointments,
        private readonly CustomerDirectory $customers,
        private readonly AppointmentPresenter $presenter,
        private readonly BusinessContext $business,
        private readonly CalendarAccess $calendars,
    ) {}

    /**
     * @return UseCaseResponse<Paginated<AppointmentData>>
     */
    public function handle(ListCustomerAppointmentsInput $input): UseCaseResponse
    {
        try {
            $input->validate();

            $businessId = $this->business->currentBusinessId();

            $this->customers->describe($businessId, $input->customerId);

            $scope = $this->calendars->scopeFor($businessId, $input->accountId);
            $booked = $this->appointments->bookedForCustomer($businessId, $input->toQuery(), $scope);

            return UseCaseResponse::success($this->presenter->describePage($businessId, $booked));
        } catch (DomainFailure $failure) {
            return UseCaseResponse::failure($failure);
        }
    }
}
