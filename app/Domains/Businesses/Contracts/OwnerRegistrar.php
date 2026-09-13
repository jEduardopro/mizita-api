<?php

declare(strict_types=1);

namespace App\Domains\Businesses\Contracts;

use App\Domains\Businesses\Exceptions\OwnerAlreadyHasBusiness;

/**
 * Makes the account that onboarded a business its owner.
 *
 * Ownership is a membership row, and memberships belong to Staff. This port is
 * the whole of what Businesses asks of that: one call, made inside the same
 * transaction as the business itself, because a business nobody can operate is
 * not a half-finished signup - it is an orphan.
 */
interface OwnerRegistrar
{
    /**
     * Registers the owner and hands back the events that registration earned,
     * for the caller to announce once the surrounding work has committed.
     *
     * The return type is list<object> on purpose. These are the neighbour's
     * events, and they travel through this domain as opaque payloads on their
     * way to the dispatcher: naming their classes here would import Staff into
     * Businesses through the back door. A file in this domain that ever does
     * instanceof on one of them is the bug this signature exists to prevent.
     *
     * @return list<object> in the order they must be announced
     *
     * @throws OwnerAlreadyHasBusiness
     */
    public function registerOwner(string $businessId, string $accountId): array;
}
