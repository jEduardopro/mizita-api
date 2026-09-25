<?php

declare(strict_types=1);

namespace App\Domains\Staff\Infrastructure\Gateways;

use App\Domains\Accounts\Contracts\TemporaryPasswordVault;
use App\Domains\Staff\Contracts\TeamTemporaryPasswords;

final class AccountsTeamTemporaryPasswords implements TeamTemporaryPasswords
{
    public function __construct(
        private readonly TemporaryPasswordVault $vault,
    ) {}

    public function revealFor(string $accountId): ?string
    {
        return $this->vault->reveal($accountId);
    }

    /**
     * @param  list<string>  $accountIds
     * @return list<string>
     */
    public function availableAmong(array $accountIds): array
    {
        return $this->vault->accountsHoldingTemporaryPassword($accountIds);
    }
}
