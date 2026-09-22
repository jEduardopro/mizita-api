<?php

declare(strict_types=1);

namespace App\Domains\Payments\Contracts;

use App\Domains\Payments\Entities\Payment;
use App\Domains\Payments\Exceptions\AppointmentAlreadyHasPayment;
use App\Domains\Payments\Exceptions\PaymentNotFound;
use App\Domains\Payments\ValueObjects\PaymentSummary;

interface PaymentRepository
{
    /**
     * @throws PaymentNotFound
     */
    public function findForAppointment(string $businessId, string $appointmentId): Payment;

    public function findForAppointmentOrNull(string $businessId, string $appointmentId): ?Payment;

    /**
     * @throws PaymentNotFound
     */
    public function findForBusiness(string $businessId, string $paymentId): Payment;

    /**
     * @throws PaymentNotFound
     */
    public function lockForBusiness(string $businessId, string $paymentId): Payment;

    /**
     * @throws AppointmentAlreadyHasPayment
     */
    public function save(Payment $payment): void;

    /**
     * @param  list<string>  $appointmentIds
     * @return array<string, PaymentSummary>
     */
    public function summariesForAppointments(string $businessId, array $appointmentIds): array;

    public function hasPaymentForAppointment(string $businessId, string $appointmentId): bool;
}
