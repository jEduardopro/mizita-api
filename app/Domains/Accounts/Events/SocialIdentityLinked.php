<?php

declare(strict_types=1);

namespace App\Domains\Accounts\Events;

use App\Domains\Accounts\ValueObjects\SocialProvider;

/**
 * Domain event: a plain readonly payload carrying identifiers, not entities.
 */
final readonly class SocialIdentityLinked
{
    public function __construct(
        public string $socialIdentityId,
        public string $accountId,
        public SocialProvider $provider,
    ) {}
}
