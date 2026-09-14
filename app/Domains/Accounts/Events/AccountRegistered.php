<?php

declare(strict_types=1);

namespace App\Domains\Accounts\Events;

final readonly class AccountRegistered
{
    public function __construct(
        public string $accountId,
    ) {}
}
