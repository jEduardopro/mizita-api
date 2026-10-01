<?php

declare(strict_types=1);

namespace App\Domains\Businesses\Contracts;

use App\Domains\Businesses\ValueObjects\MembershipRole;

interface MembershipRoles
{
    /**
     * @return array<string, MembershipRole>
     */
    public function rolesOf(string $accountId): array;
}
