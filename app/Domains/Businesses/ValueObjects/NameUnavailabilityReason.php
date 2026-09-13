<?php

declare(strict_types=1);

namespace App\Domains\Businesses\ValueObjects;

/**
 * Why a business name cannot be used, when it cannot.
 *
 * Backed by the exact strings the API answers with, so the wire contract is
 * written down once here instead of as literals in a use case and again in a
 * resource. The two cases are genuinely different advice: one says pick
 * another name, the other says write this one in letters and digits.
 */
enum NameUnavailabilityReason: string
{
    /** Another business already trades under this name. */
    case Taken = 'taken';

    /** The name produces no usable web address at all. */
    case NotSluggable = 'not_sluggable';
}
