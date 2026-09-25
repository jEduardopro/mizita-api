<?php

declare(strict_types=1);

namespace App\Domains\Appointments\Application\UseCases;

use App\Domains\Appointments\Application\Dtos\AppointmentData;
use App\Domains\Appointments\Application\Dtos\CancelAppointmentInput;
use App\Domains\Appointments\Application\Presenters\AppointmentPresenter;
use App\Domains\Appointments\Contracts\AppointmentRepository;
use App\Domains\Appointments\Contracts\CalendarAccess;
use App\Domains\Appointments\Events\AppointmentCancelled;
use App\Domains\Appointments\ValueObjects\Canceller;
use App\Shared\Application\UseCaseResponse;
use App\Shared\Contracts\BusinessContext;
use App\Shared\Contracts\Clock;
use App\Shared\Contracts\DomainFailure;
use Illuminate\Contracts\Events\Dispatcher;

final class CancelAppointment
{
    public function __construct(
        private readonly AppointmentRepository $appointments,
        private readonly AppointmentPresenter $presenter,
        private readonly BusinessContext $business,
        private readonly Clock $clock,
        private readonly CalendarAccess $calendars,
        private readonly Dispatcher $events,
    ) {}

    /**
     * @return UseCaseResponse<AppointmentData>
     */
    public function handle(CancelAppointmentInput $input): UseCaseResponse
    {
        try {
            $input->validate();

            $businessId = $this->business->currentBusinessId();
            $scope = $this->calendars->scopeFor($businessId, $input->accountId);
            $appointment = $this->appointments->findWithinScope($businessId, $input->appointmentId, $scope);

            $appointment->cancel(Canceller::Business, $this->clock->now());

            $this->appointments->save($appointment);

            $cancelled = $this->presenter->describe($businessId, $appointment);
        } catch (DomainFailure $failure) {
            return UseCaseResponse::failure($failure);
        }

        $this->events->dispatch(new AppointmentCancelled($appointment->id));

        return UseCaseResponse::success($cancelled);
    }
}
