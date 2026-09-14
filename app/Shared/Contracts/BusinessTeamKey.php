<?php

declare(strict_types=1);

namespace App\Shared\Contracts;

/**
 * The platform speaks uuids above the infrastructure layer, while Spatie scopes
 * every check by an integer team id. This port lets the HTTP edge set the
 * current team without knowing businesses even have an int primary key.
 */
interface BusinessTeamKey
{
    /**
     * Raises for a business that does not exist rather than answering with a
     * sentinel: a key nobody can hold would scope role checks to a team with no
     * assignments, which reads exactly like a caller holding no permissions.
     *
     * The exception class is left to the implementation - naming it here would
     * make the shared kernel import a domain.
     */
    public function teamKeyFor(string $businessId): int;
}
