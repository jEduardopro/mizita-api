<?php

declare(strict_types=1);

namespace App\Domains\Appointments\Application\UseCases;

use App\Domains\Appointments\Application\Dtos\CancelGuestBookingInput;
use App\Domains\Appointments\Application\Dtos\GuestBookingData;
use App\Domains\Appointments\Application\Presenters\GuestBookingPresenter;
use App\Domains\Appointments\Application\Services\GuestBookingFinder;
use App\Domains\Appointments\Contracts\AppointmentRepository;
use App\Domains\Appointments\Contracts\CancellationPolicy;
use App\Domains\Appointments\Services\AppointmentChangeWindow;
use App\Domains\Appointments\ValueObjects\Canceller;
use App\Shared\Application\UseCaseResponse;
use App\Shared\Contracts\Clock;
use App\Shared\Contracts\DomainFailure;

final class CancelGuestBooking
{
    public function __construct(
        private readonly AppointmentRepository $appointments,
        private readonly GuestBookingFinder $bookings,
        private readonly CancellationPolicy $policies,
        private readonly AppointmentChangeWindow $changeWindow,
        private readonly GuestBookingPresenter $presenter,
        private readonly Clock $clock,
    ) {}

    /**
     * @return UseCaseResponse<GuestBookingData>
     */
    public function handle(CancelGuestBookingInput $input): UseCaseResponse
    {
        try {
            $input->validate();

            $now = $this->clock->now();
            $appointment = $this->bookings->find($input->businessId, $input->credentials, $now);
            $rule = $this->policies->forBusiness($input->businessId);

            $this->changeWindow->ensureOpenFor($appointment, $rule, $now);

            $appointment->cancel(Canceller::Customer, $now);

            $this->appointments->save($appointment);

            return UseCaseResponse::success(
                $this->presenter->describe($input->businessId, $appointment, $rule, $now),
            );
        } catch (DomainFailure $failure) {
            return UseCaseResponse::failure($failure);
        }
    }
}
