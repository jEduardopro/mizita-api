<?php

declare(strict_types=1);

namespace App\Domains\Businesses\ValueObjects;

/**
 * Backed by the exact strings the API answers with, so the wire contract is
 * written down once rather than as literals in a use case and again in a resource.
 */
enum NameUnavailabilityReason: string
{
    /** Another business already trades under this name. */
    case Taken = 'taken';

    /** The name produces no usable web address at all. */
    case NotSluggable = 'not_sluggable';
}
