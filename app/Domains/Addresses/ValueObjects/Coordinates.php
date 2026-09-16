<?php

declare(strict_types=1);

namespace App\Domains\Addresses\ValueObjects;

use App\Domains\Addresses\Exceptions\InvalidCoordinates;

final readonly class Coordinates
{
    public const MINIMUM_LATITUDE = -90.0;

    public const MAXIMUM_LATITUDE = 90.0;

    public const MINIMUM_LONGITUDE = -180.0;

    public const MAXIMUM_LONGITUDE = 180.0;

    private function __construct(
        public float $latitude,
        public float $longitude,
    ) {}

    /**
     * @throws InvalidCoordinates
     */
    public static function of(float $latitude, float $longitude): self
    {
        if (! is_finite($latitude) || ! is_finite($longitude)) {
            throw InvalidCoordinates::notFinite($latitude, $longitude);
        }

        if ($latitude < self::MINIMUM_LATITUDE || $latitude > self::MAXIMUM_LATITUDE) {
            throw InvalidCoordinates::latitudeOutOfRange($latitude);
        }

        if ($longitude < self::MINIMUM_LONGITUDE || $longitude > self::MAXIMUM_LONGITUDE) {
            throw InvalidCoordinates::longitudeOutOfRange($longitude);
        }

        return new self($latitude, $longitude);
    }

    public static function restore(float $latitude, float $longitude): self
    {
        return new self($latitude, $longitude);
    }

    public function equals(self $other): bool
    {
        return $this->latitude === $other->latitude
            && $this->longitude === $other->longitude;
    }
}
