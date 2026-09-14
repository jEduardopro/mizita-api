<?php

declare(strict_types=1);

namespace App\Domains\Businesses\Contracts;

use App\Domains\Businesses\Exceptions\OwnerAlreadyHasBusiness;

/**
 * One call, made inside the same transaction as the business itself, because a
 * business nobody can operate is not a half-finished signup but an orphan.
 */
interface OwnerRegistrar
{
    /**
     * Hands the events back for the caller to announce once the surrounding work
     * has committed. list<object> on purpose: these are the neighbour's events
     * and travel as opaque payloads, because naming their classes would import
     * Staff into Businesses through the back door. An instanceof on one of them
     * anywhere in this domain is the bug this signature exists to prevent.
     *
     * @return list<object> in the order they must be announced
     *
     * @throws OwnerAlreadyHasBusiness
     */
    public function registerOwner(string $businessId, string $accountId): array;
}
