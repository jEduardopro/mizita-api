<?php

declare(strict_types=1);

namespace Tests\Support\Appointments;

use App\Domains\Appointments\Contracts\PaymentLedger;
use App\Domains\Appointments\ValueObjects\AppointmentPaymentSnapshot;

final class FakePaymentLedger implements PaymentLedger
{
    /**
     * @var array<string, array<string, AppointmentPaymentSnapshot>>
     */
    private array $paymentsByBusiness = [];

    /**
     * @var list<array{businessId: string, appointmentId: string}>
     */
    public array $reads = [];

    /**
     * @var list<array{businessId: string, appointmentIds: list<string>}>
     */
    public array $batchReads = [];

    /**
     * @var list<array{businessId: string, appointmentId: string}>
     */
    public array $paymentChecks = [];

    public function __construct(
        public readonly AppointmentJournal $journal = new AppointmentJournal,
    ) {}

    public function add(string $businessId, string $appointmentId, AppointmentPaymentSnapshot $payment): self
    {
        $this->paymentsByBusiness[$businessId][$appointmentId] = $payment;

        return $this;
    }

    public function describe(string $businessId, string $appointmentId): ?AppointmentPaymentSnapshot
    {
        $this->journal->record('payments.describe');
        $this->reads[] = ['businessId' => $businessId, 'appointmentId' => $appointmentId];

        return $this->paymentsByBusiness[$businessId][$appointmentId] ?? null;
    }

    /**
     * @param  list<string>  $appointmentIds
     * @return array<string, AppointmentPaymentSnapshot>
     */
    public function describeMany(string $businessId, array $appointmentIds): array
    {
        $this->journal->record('payments.describeMany');
        $this->batchReads[] = ['businessId' => $businessId, 'appointmentIds' => array_values($appointmentIds)];

        $known = $this->paymentsByBusiness[$businessId] ?? [];
        $found = [];

        foreach ($appointmentIds as $appointmentId) {
            if (isset($known[$appointmentId])) {
                $found[$appointmentId] = $known[$appointmentId];
            }
        }

        return $found;
    }

    public function hasPaymentFor(string $businessId, string $appointmentId): bool
    {
        $this->journal->record('payments.hasPaymentFor');
        $this->paymentChecks[] = ['businessId' => $businessId, 'appointmentId' => $appointmentId];

        return isset($this->paymentsByBusiness[$businessId][$appointmentId]);
    }
}
