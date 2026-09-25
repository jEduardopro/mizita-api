<?php

declare(strict_types=1);

namespace App\Domains\Staff\Contracts;

use DateTimeImmutable;

interface UpcomingAppointments
{
    public function existFor(string $businessId, string $staffMemberId, DateTimeImmutable $now): bool;
}
