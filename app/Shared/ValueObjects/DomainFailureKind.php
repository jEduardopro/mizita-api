<?php

declare(strict_types=1);

namespace App\Shared\ValueObjects;

enum DomainFailureKind
{
    case Invalid;

    case Conflict;

    case NotFound;

    case Unauthenticated;

    case Forbidden;
}
