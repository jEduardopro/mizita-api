<?php

declare(strict_types=1);

namespace App\Domains\Industries\Exceptions;

use DomainException;

/**
 * Deliberately not a DomainFailure: keys come from the seeder, never from a
 * request, so this firing means the catalog source is wrong - a programming
 * error, not something a user should be shown a translated sentence about.
 */
final class InvalidIndustryKey extends DomainException
{
    public static function empty(): self
    {
        return new self('An industry key cannot be empty.');
    }
}
