<?php

declare(strict_types=1);

namespace App\Shared\Contracts;

/**
 * Translates a business uuid into the internal key permissions are scoped by.
 *
 * The platform speaks uuids above the infrastructure layer, while Spatie scopes
 * every role and permission check by an integer team id. Something has to join
 * those two halves of the identity, and the identity rule says only an adapter
 * may: this port is what lets the HTTP edge set the current team without
 * knowing that businesses even have an int primary key.
 */
interface BusinessTeamKey
{
    /**
     * The permissions team key for this business.
     *
     * Raises for a business that does not exist rather than answering with a
     * sentinel: a key nobody can hold would scope role checks to a team with no
     * assignments, which reads exactly like a caller holding no permissions.
     *
     * The exception class is left to the implementation, and is a not-found
     * DomainFailure. Naming it here would make the shared kernel import the
     * domain that happens to own businesses today.
     */
    public function teamKeyFor(string $businessId): int;
}
