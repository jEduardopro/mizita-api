<?php

declare(strict_types=1);

namespace App\Domains\Accounts\Events;

/**
 * Domain event: a plain readonly payload carrying identifiers, not entities.
 */
final readonly class AccountRegistered
{
    public function __construct(
        public string $accountId,
    ) {}
}
