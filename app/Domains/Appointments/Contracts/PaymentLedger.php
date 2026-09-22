<?php

declare(strict_types=1);

namespace App\Domains\Appointments\Contracts;

use App\Domains\Appointments\ValueObjects\AppointmentPaymentSnapshot;

interface PaymentLedger
{
    public function describe(string $businessId, string $appointmentId): ?AppointmentPaymentSnapshot;

    /**
     * @param  list<string>  $appointmentIds
     * @return array<string, AppointmentPaymentSnapshot>
     */
    public function describeMany(string $businessId, array $appointmentIds): array;

    public function hasPaymentFor(string $businessId, string $appointmentId): bool;
}
