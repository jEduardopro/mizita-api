<?php

declare(strict_types=1);

namespace App\Domains\Customers\Exceptions;

use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\CountryCode;
use App\Shared\ValueObjects\DomainFailureKind;
use DomainException;

final class InvalidCustomerPhone extends DomainException implements DomainFailure
{
    public static function inCountry(string $countryCode): self
    {
        return new self("[{$countryCode}] is not a country we can dial.");
    }

    public static function malformed(string $countryCode): self
    {
        return new self("The number offered cannot be dialled in [{$countryCode}].");
    }

    public static function forCountry(CountryCode $country): self
    {
        return new self("The number offered cannot be dialled in [{$country->value}].");
    }

    public function errorCode(): string
    {
        return 'invalid_customer_phone';
    }

    public function kind(): DomainFailureKind
    {
        return DomainFailureKind::Invalid;
    }
}
