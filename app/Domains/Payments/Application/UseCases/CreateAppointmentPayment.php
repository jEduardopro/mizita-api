<?php

declare(strict_types=1);

namespace App\Domains\Payments\Application\UseCases;

use App\Domains\Payments\Application\Dtos\CreateAppointmentPaymentInput;
use App\Domains\Payments\Application\Dtos\PaymentAddOnInput;
use App\Domains\Payments\Application\Dtos\PaymentData;
use App\Domains\Payments\Application\Presenters\PaymentPresenter;
use App\Domains\Payments\Contracts\AppointmentDirectory;
use App\Domains\Payments\Contracts\BusinessProfile;
use App\Domains\Payments\Contracts\PaymentMethodCatalog;
use App\Domains\Payments\Contracts\PaymentRepository;
use App\Domains\Payments\Contracts\ServiceCatalog;
use App\Domains\Payments\Entities\Payment;
use App\Domains\Payments\ValueObjects\Money;
use App\Domains\Payments\ValueObjects\PaymentItemName;
use App\Domains\Payments\ValueObjects\ServiceSnapshot;
use App\Shared\Application\UseCaseResponse;
use App\Shared\Contracts\BusinessContext;
use App\Shared\Contracts\Clock;
use App\Shared\Contracts\DomainFailure;
use App\Shared\Contracts\IdGenerator;
use App\Shared\Contracts\TransactionManager;
use DateTimeImmutable;

final class CreateAppointmentPayment
{
    public function __construct(
        private readonly PaymentRepository $payments,
        private readonly AppointmentDirectory $appointments,
        private readonly ServiceCatalog $services,
        private readonly BusinessProfile $businesses,
        private readonly PaymentMethodCatalog $paymentMethods,
        private readonly PaymentPresenter $presenter,
        private readonly IdGenerator $ids,
        private readonly Clock $clock,
        private readonly TransactionManager $transactions,
        private readonly BusinessContext $business,
    ) {}

    /**
     * @return UseCaseResponse<PaymentData>
     */
    public function handle(CreateAppointmentPaymentInput $input): UseCaseResponse
    {
        try {
            $input->validate();

            $businessId = $this->business->currentBusinessId();

            $payment = $this->transactions->run(
                fn (): Payment => $this->collect($input, $businessId),
            );

            return UseCaseResponse::success($this->presenter->describe($businessId, $payment));
        } catch (DomainFailure $failure) {
            return UseCaseResponse::failure($failure);
        }
    }

    private function collect(CreateAppointmentPaymentInput $input, string $businessId): Payment
    {
        $currency = $this->businesses->currencyFor($businessId);
        $appointment = $this->appointments->describe($businessId, $input->appointmentId);
        $service = $this->services->describe($businessId, $appointment->serviceId, $currency);
        $now = $this->clock->now();

        $payment = Payment::open(
            id: $this->ids->next(),
            businessId: $businessId,
            appointmentId: $appointment->id,
            currency: $currency,
            now: $now,
        );

        $this->addItems($payment, $service, $input->addOns);
        $payment->applyDiscount($input->toDiscount());

        $this->recordInitialTransaction($payment, $input, $businessId, $now);

        $this->payments->save($payment);

        return $payment;
    }

    /**
     * @param  list<PaymentAddOnInput>  $addOns
     */
    private function addItems(Payment $payment, ServiceSnapshot $service, array $addOns): void
    {
        $payment->addItem(
            $this->ids->next(),
            PaymentItemName::fromString($service->name),
            $service->price,
        );

        foreach ($addOns as $addOn) {
            $payment->addItem(
                $this->ids->next(),
                $addOn->toName(),
                Money::fromCents($addOn->amountCents, $payment->currency()),
            );
        }
    }

    private function recordInitialTransaction(
        Payment $payment,
        CreateAppointmentPaymentInput $input,
        string $businessId,
        DateTimeImmutable $now,
    ): void {
        $method = $this->paymentMethods->findEnabledFor($businessId, $input->paymentMethodId);

        $payment->recordTransaction(
            $this->ids->next(),
            $method->id,
            Money::fromCents($input->amountCents, $payment->currency()),
            $now,
        );
    }
}
