<?php

declare(strict_types=1);

namespace App\Shared\Contracts;

interface BusinessAuthorization
{
    /**
     * @return array{roles: list<string>, permissions: list<string>}
     */
    public function grantsFor(string $accountId, string $businessId): array;

    public function grants(string $accountId, string $businessId, string $permission): bool;
}
