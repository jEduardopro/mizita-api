<?php

declare(strict_types=1);

namespace Tests\Support\Businesses;

use App\Domains\Businesses\Contracts\MembershipRoles;
use App\Domains\Businesses\ValueObjects\MembershipRole;

final class FakeMembershipRoles implements MembershipRoles
{
    /**
     * @var list<string>
     */
    public array $lookups = [];

    /**
     * @param  array<string, array<string, MembershipRole>>  $rolesByAccount
     */
    public function __construct(
        private readonly array $rolesByAccount = [],
    ) {}

    /**
     * @return array<string, MembershipRole>
     */
    public function rolesOf(string $accountId): array
    {
        $this->lookups[] = $accountId;

        return $this->rolesByAccount[$accountId] ?? [];
    }
}
