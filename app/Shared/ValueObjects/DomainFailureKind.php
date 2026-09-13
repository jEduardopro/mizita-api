<?php

declare(strict_types=1);

namespace App\Shared\ValueObjects;

/**
 * How a domain failure should be understood by whoever is reporting it.
 *
 * The domain says what kind of failure happened; it does not say which HTTP
 * status that becomes. Translating a kind into a status is the HTTP edge's job,
 * which is what lets the same exception be reported over a queue, a console
 * command or a future transport without the domain knowing any of them exist.
 *
 * Deliberately small: five kinds cover every failure the platform can attribute
 * to the caller. Anything else is a bug, and a bug is a 500.
 */
enum DomainFailureKind
{
    /** The request was understood but breaks a rule about its own contents. */
    case Invalid;

    /** The request is well formed but fights the current state of the resource. */
    case Conflict;

    /** The resource the request names does not exist, or must look as though it does not. */
    case NotFound;

    /** The credential offered was missing, expired or could not be verified: sign in again. */
    case Unauthenticated;

    /** The caller exists and is understood, but may not do this. */
    case Forbidden;
}
