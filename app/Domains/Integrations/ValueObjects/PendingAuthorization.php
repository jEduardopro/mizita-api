<?php

declare(strict_types=1);

namespace App\Domains\Integrations\ValueObjects;

use App\Domains\Integrations\Exceptions\CalendarAuthorizationStateInvalid;

final readonly class PendingAuthorization
{
    public function __construct(
        public string $accountId,
        public string $businessId,
        public string $staffMemberId,
    ) {}

    /**
     * @throws CalendarAuthorizationStateInvalid
     */
    public function assertIssuedTo(string $accountId): void
    {
        if ($this->accountId !== $accountId) {
            throw CalendarAuthorizationStateInvalid::issuedToAnotherAccount();
        }
    }
}
