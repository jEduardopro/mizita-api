<?php

declare(strict_types=1);

namespace App\Domains\Businesses\Exceptions;

use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\CountryCode;
use App\Shared\ValueObjects\DomainFailureKind;
use DomainException;

final class UnsupportedPhoneNumber extends DomainException implements DomainFailure
{
    public static function inCountry(string $countryCode): self
    {
        return new self("[{$countryCode}] is not a country this platform operates in.");
    }

    public static function forCountry(CountryCode $country): self
    {
        return new self("The number offered is not a valid phone number in [{$country->value}].");
    }

    public static function malformed(string $countryCode): self
    {
        return new self("The number offered for [{$countryCode}] is not shaped like a phone number.");
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
