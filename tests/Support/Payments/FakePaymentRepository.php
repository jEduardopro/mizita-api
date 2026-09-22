<?php

declare(strict_types=1);

namespace Tests\Support\Payments;

use App\Domains\Payments\Contracts\PaymentRepository;
use App\Domains\Payments\Entities\Payment;
use App\Domains\Payments\Exceptions\PaymentNotFound;
use App\Domains\Payments\ValueObjects\PaymentSummary;
use Tests\Support\FakeTransactionManager;
use Throwable;

final class FakePaymentRepository implements PaymentRepository
{
    /**
     * @var array<string, Payment>
     */
    private array $payments = [];

    private ?Throwable $saveFailure = null;

    /**
     * @var list<Payment>
     */
    public array $saved = [];

    /**
     * @var list<bool>
     */
    public array $savedInsideTransaction = [];

    /**
     * @var list<array{businessId: string, paymentId: string}>
     */
    public array $locks = [];

    /**
     * @var list<array{businessId: string, appointmentId: string}>
     */
    public array $appointmentLookups = [];

    /**
     * @var list<string>
     */
    public array $businessIdsSeen = [];

    public function __construct(
        public readonly PaymentJournal $journal = new PaymentJournal,
        private readonly ?FakeTransactionManager $transactions = null,
    ) {}

    public function store(Payment ...$payments): self
    {
        foreach ($payments as $payment) {
            $this->payments[$this->keyFor($payment->businessId, $payment->id)] = $payment;
        }

        return $this;
    }

    public function failingOnSave(Throwable $failure): self
    {
        $this->saveFailure = $failure;

        return $this;
    }

    public function findForAppointment(string $businessId, string $appointmentId): Payment
    {
        $this->journal->record('payments.findForAppointment');

        return $this->forAppointment($businessId, $appointmentId)
            ?? throw PaymentNotFound::withId($appointmentId);
    }

    public function findForAppointmentOrNull(string $businessId, string $appointmentId): ?Payment
    {
        $this->journal->record('payments.findForAppointmentOrNull');
        $this->businessIdsSeen[] = $businessId;
        $this->appointmentLookups[] = ['businessId' => $businessId, 'appointmentId' => $appointmentId];

        return $this->forAppointment($businessId, $appointmentId);
    }

    public function findForBusiness(string $businessId, string $paymentId): Payment
    {
        $this->journal->record('payments.findForBusiness');
        $this->businessIdsSeen[] = $businessId;

        return $this->payments[$this->keyFor($businessId, $paymentId)]
            ?? throw PaymentNotFound::withId($paymentId);
    }

    public function lockForBusiness(string $businessId, string $paymentId): Payment
    {
        $this->journal->record('payments.lockForBusiness');
        $this->businessIdsSeen[] = $businessId;
        $this->locks[] = ['businessId' => $businessId, 'paymentId' => $paymentId];

        return $this->payments[$this->keyFor($businessId, $paymentId)]
            ?? throw PaymentNotFound::withId($paymentId);
    }

    public function save(Payment $payment): void
    {
        $this->journal->record('payments.save');

        if ($this->saveFailure !== null) {
            throw $this->saveFailure;
        }

        $this->payments[$this->keyFor($payment->businessId, $payment->id)] = $payment;
        $this->saved[] = $payment;
        $this->savedInsideTransaction[] = $this->transactions?->isRunning() ?? false;
    }

    /**
     * @param  list<string>  $appointmentIds
     * @return array<string, PaymentSummary>
     */
    public function summariesForAppointments(string $businessId, array $appointmentIds): array
    {
        $this->journal->record('payments.summariesForAppointments');
        $this->businessIdsSeen[] = $businessId;

        $summaries = [];

        foreach ($appointmentIds as $appointmentId) {
            $payment = $this->forAppointment($businessId, $appointmentId);

            if ($payment !== null) {
                $summaries[$appointmentId] = new PaymentSummary(
                    appointmentId: $payment->appointmentId,
                    paymentId: $payment->id,
                    status: $payment->status(),
                    totalCents: $payment->total()->amount,
                    paidCents: $payment->paid()->amount,
                    currencyCode: $payment->currency()->value,
                );
            }
        }

        return $summaries;
    }

    public function hasPaymentForAppointment(string $businessId, string $appointmentId): bool
    {
        $this->journal->record('payments.hasPaymentForAppointment');
        $this->businessIdsSeen[] = $businessId;

        return $this->forAppointment($businessId, $appointmentId) !== null;
    }

    private function forAppointment(string $businessId, string $appointmentId): ?Payment
    {
        foreach ($this->payments as $payment) {
            if ($payment->businessId === $businessId && $payment->appointmentId === $appointmentId) {
                return $payment;
            }
        }

        return null;
    }

    private function keyFor(string $businessId, string $paymentId): string
    {
        return $businessId.'|'.$paymentId;
    }
}
