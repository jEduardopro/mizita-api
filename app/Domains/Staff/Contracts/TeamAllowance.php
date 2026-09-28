<?php

declare(strict_types=1);

namespace App\Domains\Staff\Contracts;

interface TeamAllowance
{
    public function includesTeam(string $businessId): bool;

    /**
     * @param  list<string>  $businessIds
     * @return list<string>
     */
    public function businessesIncludingTeam(array $businessIds): array;
}
