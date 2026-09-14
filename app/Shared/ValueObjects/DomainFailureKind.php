<?php

declare(strict_types=1);

namespace App\Shared\ValueObjects;

/**
 * The domain says what kind of failure happened, never which HTTP status that
 * becomes, so the same exception can be reported over a queue or a console
 * command without the domain knowing either exists.
 *
 * Deliberately small: anything these five do not cover is a bug, and a bug is a 500.
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
