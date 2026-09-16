<?php

declare(strict_types=1);

namespace App\Domains\Addresses\Exceptions;

use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;
use DomainException;

final class InvalidCoordinates extends DomainException implements DomainFailure
{
    private const NOT_A_NUMBER = 'NAN';

    private const POSITIVE_INFINITY = 'INF';

    private const NEGATIVE_INFINITY = '-INF';

    public static function notFinite(float $latitude, float $longitude): self
    {
        $readableLatitude = self::readable($latitude);
        $readableLongitude = self::readable($longitude);

        return new self("The coordinate [latitude {$readableLatitude}, longitude {$readableLongitude}] is not a finite number.");
    }

    public static function missingLatitude(string $longitude): self
    {
        return new self("The longitude [{$longitude}] was given without a latitude.");
    }

    public static function missingLongitude(string $latitude): self
    {
        return new self("The latitude [{$latitude}] was given without a longitude.");
    }

    public static function latitudeOutOfRange(float $latitude): self
    {
        return new self("The latitude [{$latitude}] is outside the range a coordinate may take.");
    }

    public static function longitudeOutOfRange(float $longitude): self
    {
        return new self("The longitude [{$longitude}] is outside the range a coordinate may take.");
    }

    public function errorCode(): string
    {
        return 'invalid_coordinates';
    }

    public function kind(): DomainFailureKind
    {
        return DomainFailureKind::Invalid;
    }

    private static function readable(float $value): string
    {
        if (is_nan($value)) {
            return self::NOT_A_NUMBER;
        }

        if (is_infinite($value)) {
            return $value > 0 ? self::POSITIVE_INFINITY : self::NEGATIVE_INFINITY;
        }

        return (string) $value;
    }
}
