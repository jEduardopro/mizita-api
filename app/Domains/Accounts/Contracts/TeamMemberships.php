<?php

declare(strict_types=1);

namespace App\Domains\Accounts\Contracts;

use App\Domains\Accounts\Exceptions\AccountHasUpcomingAppointments;

interface TeamMemberships
{
    public function ownedBusinessIdOf(string $accountId): ?string;

    public function hasUpcomingAppointmentsOutsideOwnedBusiness(string $accountId): bool;

    /**
     * @throws AccountHasUpcomingAppointments
     */
    public function leaveTeamsNotOwned(string $accountId): void;
}
