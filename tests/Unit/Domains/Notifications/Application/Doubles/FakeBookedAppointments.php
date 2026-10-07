<?php

declare(strict_types=1);

namespace Tests\Unit\Domains\Notifications\Application\Doubles;

use App\Domains\Notifications\Contracts\BookedAppointments;
use App\Domains\Notifications\Exceptions\NotifiedAppointmentNotFound;
use App\Domains\Notifications\ValueObjects\BookedAppointment;

final class FakeBookedAppointments implements BookedAppointments
{
    /**
     * @var array<string, BookedAppointment>
     */
    private array $appointments = [];

    /**
     * @var list<string>
     */
    public array $lookups = [];

    public function __construct(
        private readonly NotificationsJournal $journal = new NotificationsJournal,
    ) {}

    public function add(BookedAppointment ...$appointments): self
    {
        foreach ($appointments as $appointment) {
            $this->appointments[$appointment->appointmentId] = $appointment;
        }

        return $this;
    }

    public function recipientOf(string $appointmentId): BookedAppointment
    {
        $this->journal->record('appointments.recipientOf');
        $this->lookups[] = $appointmentId;

        return $this->appointments[$appointmentId] ?? throw NotifiedAppointmentNotFound::withId($appointmentId);
    }
}
