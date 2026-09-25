<?php

declare(strict_types=1);

namespace App\Domains\Accounts\Application\Dtos;

final readonly class InvitedAccountData
{
    public function __construct(
        public string $accountId,
        public ?string $temporaryPassword,
        public bool $created,
    ) {}

    public static function forExistingAccount(string $accountId): self
    {
        return new self(accountId: $accountId, temporaryPassword: null, created: false);
    }

    public static function forNewAccount(string $accountId, ?string $temporaryPassword): self
    {
        return new self(accountId: $accountId, temporaryPassword: $temporaryPassword, created: true);
    }
}
