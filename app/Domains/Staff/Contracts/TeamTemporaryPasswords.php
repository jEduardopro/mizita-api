<?php

declare(strict_types=1);

namespace App\Domains\Staff\Contracts;

interface TeamTemporaryPasswords
{
    public function revealFor(string $accountId): ?string;

    /**
     * @param  list<string>  $accountIds
     * @return list<string>
     */
    public function availableAmong(array $accountIds): array;
}
