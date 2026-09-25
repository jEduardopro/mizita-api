<?php

declare(strict_types=1);

namespace App\Domains\Accounts\Contracts;

use App\Domains\Accounts\Exceptions\AccountNotFound;
use SensitiveParameter;

interface TemporaryPasswordVault
{
    /**
     * @throws AccountNotFound
     */
    public function keep(string $accountId, #[SensitiveParameter] string $temporaryPassword): void;

    public function reveal(string $accountId): ?string;

    public function discard(string $accountId): void;

    /**
     * @param  list<string>  $accountIds
     * @return list<string>
     */
    public function accountsHoldingTemporaryPassword(array $accountIds): array;
}
