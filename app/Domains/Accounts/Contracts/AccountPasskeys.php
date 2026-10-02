<?php

declare(strict_types=1);

namespace App\Domains\Accounts\Contracts;

interface AccountPasskeys
{
    public function deleteAllOf(string $accountId): void;
}
