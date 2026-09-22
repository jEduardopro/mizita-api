<?php

declare(strict_types=1);

namespace App\Domains\Appointments\Infrastructure\Gateways;

use App\Domains\Appointments\Contracts\PaymentLedger;
use App\Domains\Appointments\ValueObjects\AppointmentPaymentSnapshot;
use App\Domains\Appointments\ValueObjects\AppointmentPaymentStatus;
use App\Domains\Payments\Contracts\PaymentRepository;
use App\Domains\Payments\ValueObjects\PaymentStatus;
use App\Domains\Payments\ValueObjects\PaymentSummary;

final class PaymentsPaymentLedger implements PaymentLedger
{
    public function __construct(
        private readonly PaymentRepository $payments,
    ) {}

    public function describe(string $businessId, string $appointmentId): ?AppointmentPaymentSnapshot
    {
        return $this->describeMany($businessId, [$appointmentId])[$appointmentId] ?? null;
    }

    /**
     * @param  list<string>  $appointmentIds
     * @return array<string, AppointmentPaymentSnapshot>
     */
    public function describeMany(string $businessId, array $appointmentIds): array
    {
        if ($appointmentIds === []) {
            return [];
        }

        $summaries = $this->payments->summariesForAppointments(
            $businessId,
            array_values(array_unique($appointmentIds)),
        );

        return array_map(self::snapshotOf(...), $summaries);
    }

    public function hasPaymentFor(string $businessId, string $appointmentId): bool
    {
        return $this->payments->hasPaymentForAppointment($businessId, $appointmentId);
    }

    private static function snapshotOf(PaymentSummary $summary): AppointmentPaymentSnapshot
    {
        return new AppointmentPaymentSnapshot(
            id: $summary->paymentId,
            status: self::statusOf($summary->status),
            totalCents: $summary->totalCents,
            paidCents: $summary->paidCents,
            balanceCents: $summary->totalCents - $summary->paidCents,
            currencyCode: $summary->currencyCode,
        );
    }

    private static function statusOf(PaymentStatus $status): AppointmentPaymentStatus
    {
        return match ($status) {
            PaymentStatus::Pending => AppointmentPaymentStatus::Pending,
            PaymentStatus::PartiallyPaid => AppointmentPaymentStatus::PartiallyPaid,
            PaymentStatus::Paid => AppointmentPaymentStatus::Paid,
        };
    }
}
