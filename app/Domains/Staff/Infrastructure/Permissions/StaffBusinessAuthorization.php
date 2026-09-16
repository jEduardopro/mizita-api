<?php

declare(strict_types=1);

namespace App\Domains\Staff\Infrastructure\Permissions;

use App\Models\User;
use App\Shared\Contracts\BusinessAuthorization;
use App\Shared\Contracts\BusinessTeamKey;

final class StaffBusinessAuthorization implements BusinessAuthorization
{
    /**
     * @var array{roles: list<string>, permissions: list<string>}
     */
    private const NOTHING_GRANTED = ['roles' => [], 'permissions' => []];

    public function __construct(
        private readonly BusinessTeamKey $teamKeys,
        private readonly StaffRoleAssignments $assignments,
    ) {}

    /**
     * @return array{roles: list<string>, permissions: list<string>}
     */
    public function grantsFor(string $accountId, string $businessId): array
    {
        $account = User::query()->where('uuid', $accountId)->first();

        if ($account === null) {
            return self::NOTHING_GRANTED;
        }

        return $this->assignments->grantsFor($account, $this->teamKeys->teamKeyFor($businessId));
    }

    public function grants(string $accountId, string $businessId, string $permission): bool
    {
        return in_array(
            $permission,
            $this->grantsFor($accountId, $businessId)['permissions'],
            strict: true,
        );
    }
}
