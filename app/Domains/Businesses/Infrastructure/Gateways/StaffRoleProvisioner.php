<?php

declare(strict_types=1);

namespace App\Domains\Businesses\Infrastructure\Gateways;

use App\Domains\Businesses\Contracts\RoleProvisioner;
use App\Domains\Businesses\Exceptions\BusinessNotFound;
use App\Domains\Staff\Infrastructure\Permissions\BusinessRoleTemplates;
use App\Shared\Contracts\BusinessTeamKey;

/**
 * Staff's class name stops at this file, so nothing above Infrastructure learns
 * that roles are a Staff concern - or that they are Spatie rows at all.
 *
 * BusinessNotFound travels out unchanged, unlike the Staff exception
 * StaffOwnerRegistrar translates: it is already this domain's own, and it means
 * the business row is missing mid-transaction - a bug, not a caller's problem.
 */
final class StaffRoleProvisioner implements RoleProvisioner
{
    public function __construct(
        private readonly BusinessTeamKey $teamKeys,
        private readonly BusinessRoleTemplates $templates,
    ) {}

    /**
     * @throws BusinessNotFound when the business has not been saved yet
     */
    public function provisionFor(string $businessId): void
    {
        $this->templates->cloneFor($this->teamKeys->teamKeyFor($businessId));
    }
}
