<?php

declare(strict_types=1);

namespace App\Domains\Businesses\Infrastructure\Gateways;

use App\Domains\Businesses\Contracts\RoleProvisioner;
use App\Domains\Businesses\Exceptions\BusinessNotFound;
use App\Domains\Staff\Infrastructure\Permissions\BusinessRoleTemplates;
use App\Shared\Contracts\BusinessTeamKey;

final class StaffRoleProvisioner implements RoleProvisioner
{
    public function __construct(
        private readonly BusinessTeamKey $teamKeys,
        private readonly BusinessRoleTemplates $templates,
    ) {}

    /**
     * @throws BusinessNotFound
     */
    public function provisionFor(string $businessId): void
    {
        $this->templates->cloneFor($this->teamKeys->teamKeyFor($businessId));
    }
}
