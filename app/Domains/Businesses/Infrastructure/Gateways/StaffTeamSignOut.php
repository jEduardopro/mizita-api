<?php

declare(strict_types=1);

namespace App\Domains\Businesses\Infrastructure\Gateways;

use App\Domains\Businesses\Contracts\TeamSignOut;
use App\Domains\Staff\Contracts\TeamRoster;
use App\Shared\Contracts\AccountSessions;

final class StaffTeamSignOut implements TeamSignOut
{
    public function __construct(
        private readonly TeamRoster $roster,
        private readonly AccountSessions $sessions,
    ) {}

    public function signOutTeamOf(string $businessId): void
    {
        $this->sessions->endAll($this->roster->accountIdsOnTeam($businessId));
    }
}
