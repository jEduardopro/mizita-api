<?php

declare(strict_types=1);

namespace App\Domains\Accounts\Application\Dtos;

use App\Domains\Accounts\Entities\Account;

final readonly class AuthenticatedAccountData
{
    private function __construct(
        public string $id,
        public string $name,
        public string $email,
        public bool $isNewAccount,
        public bool $requiresSecondFactor,
    ) {}

    public static function forExistingAccount(Account $account): self
    {
        return new self(
            id: $account->id,
            name: $account->name(),
            email: $account->email(),
            isNewAccount: false,
            requiresSecondFactor: $account->requiresSecondFactor(),
        );
    }

    public static function forNewAccount(Account $account): self
    {
        return new self(
            id: $account->id,
            name: $account->name(),
            email: $account->email(),
            isNewAccount: true,
            requiresSecondFactor: false,
        );
    }
}
