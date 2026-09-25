<?php

declare(strict_types=1);

namespace App\Domains\Payments\Application\UseCases;

use App\Domains\Payments\Application\Dtos\PaymentData;
use App\Domains\Payments\Application\Dtos\RecordPaymentTransactionInput;
use App\Domains\Payments\Application\Presenters\PaymentPresenter;
use App\Domains\Payments\Contracts\AppointmentDirectory;
use App\Domains\Payments\Contracts\CalendarAccess;
use App\Domains\Payments\Contracts\PaymentMethodCatalog;
use App\Domains\Payments\Contracts\PaymentRepository;
use App\Domains\Payments\Entities\Payment;
use App\Domains\Payments\Entities\PaymentMethod;
use App\Domains\Payments\Exceptions\PaymentAppointmentNotFound;
use App\Domains\Payments\Exceptions\PaymentNotFound;
use App\Domains\Payments\ValueObjects\CalendarScope;
use App\Domains\Payments\ValueObjects\Money;
use App\Domains\Payments\ValueObjects\PaymentBreakdown;
use App\Shared\Application\UseCaseResponse;
use App\Shared\Contracts\BusinessContext;
use App\Shared\Contracts\Clock;
use App\Shared\Contracts\DomainFailure;
use App\Shared\Contracts\IdGenerator;
use App\Shared\Contracts\TransactionManager;

final class RecordPaymentTransaction
{
    public function __construct(
        private readonly PaymentRepository $payments,
        private readonly PaymentMethodCatalog $paymentMethods,
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
    public function handle(RecordPaymentTransactionInput $input): UseCaseResponse
    {
        try {
            $input->validate();

            $businessId = $this->business->currentBusinessId();
            $scope = $this->calendars->scopeFor($businessId, $input->actorAccountId);
            $method = $this->paymentMethods->findEnabledFor($businessId, $input->paymentMethodId);

            $payment = $this->transactions->run(
                fn (): Payment => $this->record($input, $businessId, $scope, $method),
            );

            return UseCaseResponse::success($this->presenter->describe($businessId, $payment));
        } catch (DomainFailure $failure) {
            return UseCaseResponse::failure($failure);
        }
    }

    private function record(
        RecordPaymentTransactionInput $input,
        string $businessId,
        CalendarScope $scope,
        PaymentMethod $method,
    ): Payment {
        $payment = $this->payments->lockForBusiness($businessId, $input->paymentId);
        $this->ensurePaymentIsInScope($businessId, $scope, $payment);

        $payment->recordTransaction(
            $this->ids->next(),
            $method->id,
            $input->actorAccountId,
            PaymentBreakdown::none($payment->currency()),
            Money::fromCents($input->amountCents, $payment->currency()),
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
