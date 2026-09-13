<?php

declare(strict_types=1);

namespace App\Shared\Contracts;

/**
 * Answers which businesses an account is allowed to operate.
 *
 * A staff row is the membership: this is the port that turns that fact into
 * something the rest of the platform can ask about without importing Staff.
 * It reports membership only - who may operate what - and never which business
 * is current, which stays BusinessContext's single responsibility.
 */
interface BusinessMembership
{
    /**
     * The businesses this account may operate, owner membership first, then by
     * membership age. An empty list means the account is not a business user.
     *
     * @return list<string> business uuids
     */
    public function businessIdsFor(string $accountId): array;
}
