<?php

declare(strict_types=1);

namespace App\Domains\Businesses\Exceptions;

use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\CountryCode;
use App\Shared\ValueObjects\DomainFailureKind;
use DomainException;

/**
 * One class for both ways this happens - an unserved country, and a number the
 * numbering plan rejects - because to the person filling in the form they are
 * the same fact. Two error codes would be a choice they have no use for.
 *
 * Distinct from Shared\ValueObjects\InvalidPhoneNumber, which stays an
 * InvalidArgumentException because it fires on a bug and is a 500. This one is a
 * caller-actionable rejection, so it classifies itself as a 422.
 */
final class UnsupportedPhoneNumber extends DomainException implements DomainFailure
{
    /** The caller named a country the platform does not operate in. */
    public static function inCountry(string $countryCode): self
    {
        return new self("[{$countryCode}] is not a country this platform operates in.");
    }

    /**
     * The number itself is deliberately absent from the message: these strings
     * end up in logs, and a phone number is personal data.
     */
    public static function forCountry(CountryCode $country): self
    {
        return new self("The number offered is not a valid phone number in [{$country->value}].");
    }

    public function errorCode(): string
    {
        return 'unsupported_phone_number';
    }

    public function kind(): DomainFailureKind
    {
        return DomainFailureKind::Invalid;
    }
}
