<?php

declare(strict_types=1);

namespace App\Shared\ValueObjects;

use InvalidArgumentException;

/**
 * A phone number that could not be built from the parts it was given.
 *
 * Deliberately not a DomainFailure: by the time a national number reaches this
 * value object it has already passed a FormRequest, so this firing means the
 * input never went through validation - a programming error, not something a
 * user should be shown a translated sentence about.
 */
final class InvalidPhoneNumber extends InvalidArgumentException
{
    public static function empty(): self
    {
        return new self('A phone number cannot be empty.');
    }

    public static function malformed(string $nationalNumber): self
    {
        return new self("[{$nationalNumber}] is not a valid national phone number.");
    }
}
