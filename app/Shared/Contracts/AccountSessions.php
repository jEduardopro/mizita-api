<?php

declare(strict_types=1);

namespace App\Shared\Contracts;

interface AccountSessions
{
    /**
     * @param  list<string>  $accountIds
     */
    public function endAll(array $accountIds): void;

    public function endAllExcept(string $accountId, string $keptSessionId): void;
}
