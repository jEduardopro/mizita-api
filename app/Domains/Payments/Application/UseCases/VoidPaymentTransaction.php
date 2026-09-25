<?php

declare(strict_types=1);

namespace App\Domains\Payments\Application\UseCases;

use App\Domains\Payments\Application\Dtos\PaymentData;
use App\Domains\Payments\Application\Dtos\VoidPaymentTransactionInput;
use App\Domains\Payments\Application\Presenters\PaymentPresenter;
use App\Domains\Payments\Contracts\AppointmentDirectory;
use App\Domains\Payments\Contracts\CalendarAccess;
use App\Domains\Payments\Contracts\PaymentRepository;
use App\Domains\Payments\Entities\Payment;
use App\Domains\Payments\Exceptions\PaymentAppointmentNotFound;
use App\Domains\Payments\Exceptions\PaymentNotFound;
use App\Domains\Payments\ValueObjects\CalendarScope;
use App\Shared\Application\UseCaseResponse;
use App\Shared\Contracts\BusinessContext;
use App\Shared\Contracts\Clock;
use App\Shared\Contracts\DomainFailure;
use App\Shared\Contracts\IdGenerator;
use App\Shared\Contracts\TransactionManager;

final class VoidPaymentTransaction
{
    public function __construct(
        private readonly PaymentRepository $payments,
        private readonly AppointmentDirectory $appointments,
        private readonly CalendarAccess $calendars,
        private readonly PaymentPresenter $presenter,
        private readonly IdGenerator $ids,
        private readonly Clock $clock,
        private readonly TransactionManager $transactions,
        private readonly BusinessContext $business,
    ) {}

    /**
     * @return UseCaseResponse<PaymentData>
     */
    public function handle(VoidPaymentTransactionInput $input): UseCaseResponse
    {
        try {
            $input->validate();

            $businessId = $this->business->currentBusinessId();
            $scope = $this->calendars->scopeFor($businessId, $input->actorAccountId);

            $payment = $this->transactions->run(
                fn (): Payment => $this->voidTransaction($input, $businessId, $scope),
            );

            return UseCaseResponse::success($this->presenter->describe($businessId, $payment));
        } catch (DomainFailure $failure) {
            return UseCaseResponse::failure($failure);
        }
    }

    private function voidTransaction(
        VoidPaymentTransactionInput $input,
        string $businessId,
        CalendarScope $scope,
    ): Payment {
        $payment = $this->payments->lockForBusiness($businessId, $input->paymentId);
        $this->ensurePaymentIsInScope($businessId, $scope, $payment);

        $payment->voidTransaction(
            $this->ids->next(),
            $input->transactionId,
            $input->actorAccountId,
            $this->clock->now(),
        );

        $this->payments->save($payment);

        return $payment;
    }

    /**
     * @throws PaymentAppointmentNotFound
     * @throws PaymentNotFound
     */
    private function ensurePaymentIsInScope(string $businessId, CalendarScope $scope, Payment $payment): void
    {
        if (! $scope->isRestricted()) {
            return;
        }

        $appointment = $this->appointments->describe($businessId, $payment->appointmentId);

        if (! $scope->covers($appointment)) {
            throw PaymentNotFound::withId($payment->id);
        }
    }
}
