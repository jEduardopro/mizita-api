<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Shared\Contracts\BusinessAuthorization;

final class FakeBusinessAuthorization implements BusinessAuthorization
{
    /**
     * @var array<string, array{roles: list<string>, permissions: list<string>}>
     */
    private array $grantsByAccountAndBusiness = [];

    /**
     * @var list<array{accountId: string, businessId: string, permission: string}>
     */
    public array $checks = [];

    /**
     * @var list<array{accountId: string, businessId: string}>
     */
    public array $lookups = [];

    /**
     * @param  list<string>  $permissions
     * @param  list<string>  $roles
     */
    public static function granting(
        string $accountId,
        string $businessId,
        array $permissions,
        array $roles = [],
    ): self {
        return (new self)->add($accountId, $businessId, $permissions, $roles);
    }

    /**
     * @param  list<string>  $permissions
     * @param  list<string>  $roles
     */
    public function add(string $accountId, string $businessId, array $permissions, array $roles = []): self
    {
        $this->grantsByAccountAndBusiness[$this->keyFor($accountId, $businessId)] = [
            'roles' => $roles,
            'permissions' => $permissions,
        ];

        return $this;
    }

    /**
     * @return array{roles: list<string>, permissions: list<string>}
     */
    public function grantsFor(string $accountId, string $businessId): array
    {
        $this->lookups[] = ['accountId' => $accountId, 'businessId' => $businessId];

        return $this->grantsByAccountAndBusiness[$this->keyFor($accountId, $businessId)]
            ?? ['roles' => [], 'permissions' => []];
    }

    public function grants(string $accountId, string $businessId, string $permission): bool
    {
        $this->checks[] = [
            'accountId' => $accountId,
            'businessId' => $businessId,
            'permission' => $permission,
        ];

        return in_array($permission, $this->grantsFor($accountId, $businessId)['permissions'], strict: true);
    }

    /**
     * @return array{accountId: string, businessId: string, permission: string}
     */
    public function lastCheck(): array
    {
        return $this->checks[count($this->checks) - 1];
    }

    private function keyFor(string $accountId, string $businessId): string
    {
        return $accountId.'|'.$businessId;
    }
}
