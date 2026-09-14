<?php

declare(strict_types=1);

namespace App\Shared\Contracts;

interface BusinessTeamKey
{
    public function teamKeyFor(string $businessId): int;
}
