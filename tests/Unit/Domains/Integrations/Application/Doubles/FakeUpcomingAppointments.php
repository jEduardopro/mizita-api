<?php

declare(strict_types=1);

namespace Tests\Unit\Domains\Integrations\Application\Doubles;

use App\Domains\Integrations\Contracts\UpcomingAppointments;
use DateTimeImmutable;

final class FakeUpcomingAppointments implements UpcomingAppointments
{
    /**
     * @var list<array{businessId: string, staffMemberId: string, now: DateTimeImmutable}>
     */
    public array $lookups = [];

    /**
     * @var list<string>
     */
    private array $appointmentIds;

    public function __construct(string ...$appointmentIds)
    {
        $this->appointmentIds = array_values($appointmentIds);
    }

    public function activeIdsFor(string $businessId, string $staffMemberId, DateTimeImmutable $now): array
    {
        $this->lookups[] = ['businessId' => $businessId, 'staffMemberId' => $staffMemberId, 'now' => $now];

        return $this->appointmentIds;
    }
}
