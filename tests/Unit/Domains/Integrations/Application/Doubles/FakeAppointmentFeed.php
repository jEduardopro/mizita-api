<?php

declare(strict_types=1);

namespace Tests\Unit\Domains\Integrations\Application\Doubles;

use App\Domains\Integrations\Contracts\AppointmentFeed;
use App\Domains\Integrations\Exceptions\CalendarAppointmentNotFound;
use App\Domains\Integrations\ValueObjects\AppointmentSnapshot;

final class FakeAppointmentFeed implements AppointmentFeed
{
    /**
     * @var array<string, AppointmentSnapshot>
     */
    private array $snapshots = [];

    public function __construct(
        private readonly IntegrationsJournal $journal = new IntegrationsJournal,
    ) {}

    public function add(AppointmentSnapshot $snapshot): self
    {
        $this->snapshots[$snapshot->appointmentId] = $snapshot;

        return $this;
    }

    public function snapshotOf(string $appointmentId): AppointmentSnapshot
    {
        $this->journal->record('appointments.snapshotOf');

        return $this->snapshots[$appointmentId] ?? throw CalendarAppointmentNotFound::withId($appointmentId);
    }
}
