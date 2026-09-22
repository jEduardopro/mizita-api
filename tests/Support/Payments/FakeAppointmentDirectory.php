<?php

declare(strict_types=1);

namespace Tests\Support\Payments;

use App\Domains\Payments\Contracts\AppointmentDirectory;
use App\Domains\Payments\Exceptions\PaymentAppointmentNotFound;
use App\Domains\Payments\ValueObjects\AppointmentSnapshot;

final class FakeAppointmentDirectory implements AppointmentDirectory
{
    /**
     * @var array<string, array<string, AppointmentSnapshot>>
     */
    private array $appointmentsByBusiness = [];

    /**
     * @var list<array{businessId: string, appointmentId: string}>
     */
    public array $reads = [];

    public function __construct(
        public readonly PaymentJournal $journal = new PaymentJournal,
    ) {}

    public function add(string $businessId, AppointmentSnapshot ...$appointments): self
    {
        foreach ($appointments as $appointment) {
            $this->appointmentsByBusiness[$businessId][$appointment->id] = $appointment;
        }

        return $this;
    }

    public function describe(string $businessId, string $appointmentId): AppointmentSnapshot
    {
        $this->journal->record('appointments.describe');
        $this->reads[] = ['businessId' => $businessId, 'appointmentId' => $appointmentId];

        return $this->appointmentsByBusiness[$businessId][$appointmentId]
            ?? throw PaymentAppointmentNotFound::withId($appointmentId);
    }
}
