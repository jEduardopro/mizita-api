<?php

declare(strict_types=1);

namespace App\Shared\Contracts;

/**
 * Turns "a staff row is the membership" into something the rest of the platform
 * can ask about without importing Staff. It reports membership only, never which
 * business is current - that stays BusinessContext's responsibility.
 */
interface BusinessMembership
{
    /**
     * Owner membership first, then by membership age. An empty list means the
     * account is not a business user.
     *
     * @return list<string> business uuids
     */
    public function businessIdsFor(string $accountId): array;
}
