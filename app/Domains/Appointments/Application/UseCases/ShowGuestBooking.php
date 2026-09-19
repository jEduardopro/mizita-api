<?php

declare(strict_types=1);

namespace App\Domains\Appointments\Application\UseCases;

use App\Domains\Appointments\Application\Dtos\GuestBookingData;
use App\Domains\Appointments\Application\Dtos\ShowGuestBookingInput;
use App\Domains\Appointments\Application\Presenters\GuestBookingPresenter;
use App\Domains\Appointments\Application\Services\GuestBookingFinder;
use App\Domains\Appointments\Contracts\CancellationPolicy;
use App\Shared\Application\UseCaseResponse;
use App\Shared\Contracts\Clock;
use App\Shared\Contracts\DomainFailure;

final class ShowGuestBooking
{
    public function __construct(
        private readonly GuestBookingFinder $bookings,
        private readonly CancellationPolicy $policies,
        private readonly GuestBookingPresenter $presenter,
        private readonly Clock $clock,
    ) {}

    /**
     * @return UseCaseResponse<GuestBookingData>
     */
    public function handle(ShowGuestBookingInput $input): UseCaseResponse
    {
        try {
            $input->validate();

            $now = $this->clock->now();
            $appointment = $this->bookings->find($input->businessId, $input->credentials, $now);

            return UseCaseResponse::success($this->presenter->describe(
                $input->businessId,
                $appointment,
                $this->policies->forBusiness($input->businessId),
                $now,
            ));
        } catch (DomainFailure $failure) {
            return UseCaseResponse::failure($failure);
        }
    }
}
