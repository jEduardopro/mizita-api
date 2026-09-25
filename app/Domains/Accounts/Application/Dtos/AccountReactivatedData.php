<?php

declare(strict_types=1);

namespace App\Domains\Accounts\Application\Dtos;

use App\Domains\Accounts\Entities\Account;

final readonly class AccountReactivatedData
{
    private function __construct(
        public string $accountId,
        public bool $requiresSecondFactor,
    ) {}

    public static function of(Account $account): self
    {
        return new self(
            accountId: $account->id,
            requiresSecondFactor: $account->requiresSecondFactor(),
        );
    }
}
