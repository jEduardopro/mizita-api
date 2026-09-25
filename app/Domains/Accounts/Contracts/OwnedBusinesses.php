<?php

declare(strict_types=1);

namespace App\Domains\Accounts\Contracts;

use App\Domains\Accounts\ValueObjects\ClosedBusinessSnapshot;
use App\Domains\Accounts\ValueObjects\OwnedBusinessSnapshot;

interface OwnedBusinesses
{
    public function describe(string $businessId): OwnedBusinessSnapshot;

    public function close(string $businessId, string $ownerAccountId): void;

    public function closedBusinessOf(string $accountId): ?ClosedBusinessSnapshot;

    public function reopen(string $businessId, string $ownerAccountId): void;
}
