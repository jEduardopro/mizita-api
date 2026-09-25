<?php

declare(strict_types=1);

namespace App\Domains\Payments\Application\UseCases;

use App\Domains\Payments\Application\Dtos\PaymentData;
use App\Domains\Payments\Application\Dtos\ShowAppointmentPaymentInput;
use App\Domains\Payments\Application\Presenters\PaymentPresenter;
use App\Domains\Payments\Contracts\AppointmentDirectory;
use App\Domains\Payments\Contracts\CalendarAccess;
use App\Domains\Payments\Contracts\PaymentRepository;
use App\Domains\Payments\Exceptions\PaymentAccountNotFound;
use App\Domains\Payments\Exceptions\PaymentAppointmentNotFound;
use App\Shared\Application\UseCaseResponse;
use App\Shared\Contracts\BusinessContext;
use App\Shared\Contracts\DomainFailure;

final class ShowAppointmentPayment
{
    public function __construct(
        private readonly PaymentRepository $payments,
        private readonly AppointmentDirectory $appointments,
        private readonly CalendarAccess $calendars,
        private readonly PaymentPresenter $presenter,
        private readonly BusinessContext $business,
    ) {}

    /**
     * @return UseCaseResponse<PaymentData|null>
     */
    public function handle(ShowAppointmentPaymentInput $input): UseCaseResponse
    {
        try {
            $input->validate();

            $businessId = $this->business->currentBusinessId();
            $this->ensureAppointmentIsInScope($businessId, $input);

            $payment = $this->payments->findForAppointmentOrNull($businessId, $input->appointmentId);

            if ($payment === null) {
                return UseCaseResponse::success(null);
            }

            return UseCaseResponse::success($this->presenter->describe($businessId, $payment));
        } catch (DomainFailure $failure) {
            return UseCaseResponse::failure($failure);
        }
    }

    /**
     * @throws PaymentAccountNotFound
     * @throws PaymentAppointmentNotFound
     */
    private function ensureAppointmentIsInScope(string $businessId, ShowAppointmentPaymentInput $input): void
    {
        $scope = $this->calendars->scopeFor($businessId, $input->actorAccountId);

        if (! $scope->isRestricted()) {
            return;
        }

        $appointment = $this->appointments->describe($businessId, $input->appointmentId);

        if (! $scope->covers($appointment)) {
            throw PaymentAppointmentNotFound::withId($input->appointmentId);
        }
    }
}
