<?php

declare(strict_types=1);

namespace Tests\Support\Staff;

use App\Domains\Staff\Contracts\UpcomingAppointments;
use DateTimeImmutable;

final class FakeUpcomingAppointments implements UpcomingAppointments
{
    /**
     * @var array<string, true>
     */
    private array $busy = [];

    /**
     * @var list<array{businessId: string, staffMemberId: string, now: DateTimeImmutable}>
     */
    public array $checks = [];

    public function busy(string $businessId, string $staffMemberId): self
    {
        $this->busy[$businessId.'|'.$staffMemberId] = true;

        return $this;
    }

    public function existFor(string $businessId, string $staffMemberId, DateTimeImmutable $now): bool
    {
        $this->checks[] = ['businessId' => $businessId, 'staffMemberId' => $staffMemberId, 'now' => $now];

        return isset($this->busy[$businessId.'|'.$staffMemberId]);
    }
}
