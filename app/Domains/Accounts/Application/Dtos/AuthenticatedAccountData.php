<?php

declare(strict_types=1);

namespace App\Domains\Accounts\Application\Dtos;

use App\Domains\Accounts\Entities\Account;

/**
 * Output boundary. Entities never leave the application layer, so use cases
 * return this instead.
 *
 * isNewAccount is an observation about what just happened, for a caller that
 * wants to greet a first-time visitor differently. It is never an input.
 */
final readonly class AuthenticatedAccountData
{
    private function __construct(
        public string $id,
        public string $name,
        public string $email,
        public bool $isNewAccount,
    ) {}

    public static function forExistingAccount(Account $account): self
    {
        return new self(
            id: $account->id,
            name: $account->name(),
            email: $account->email(),
            isNewAccount: false,
        );
    }

    public static function forNewAccount(Account $account): self
    {
        return new self(
            id: $account->id,
            name: $account->name(),
            email: $account->email(),
            isNewAccount: true,
        );
    }
}
