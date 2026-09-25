<?php

declare(strict_types=1);

namespace App\Domains\Integrations\Contracts;

use DateTimeImmutable;

interface UpcomingAppointments
{
    /**
     * @return list<string>
     */
    public function activeIdsFor(string $businessId, string $staffMemberId, DateTimeImmutable $now): array;
}
