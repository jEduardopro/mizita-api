<?php

declare(strict_types=1);

namespace App\Domains\Businesses\Contracts;

interface TeamSignOut
{
    public function signOutTeamOf(string $businessId): void;
}
