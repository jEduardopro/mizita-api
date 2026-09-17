<?php

declare(strict_types=1);

namespace App\Domains\Businesses\Application\Dtos;

use App\Domains\Businesses\Exceptions\InvalidBusinessCoordinates;
use App\Domains\Businesses\Exceptions\InvalidBusinessCurrency;
use App\Domains\Businesses\Exceptions\InvalidBusinessTimezone;
use App\Domains\Businesses\ValueObjects\CurrencyCode;
use App\Domains\Businesses\ValueObjects\Timezone;

final readonly class LocationInput
{
    public const MINIMUM_LATITUDE = -90.0;

    public const MAXIMUM_LATITUDE = 90.0;

    public const MINIMUM_LONGITUDE = -180.0;

    public const MAXIMUM_LONGITUDE = 180.0;

    public function __construct(
        public string $street,
        public ?string $city,
        public ?string $stateId,
        public ?string $postalCode,
        public string $countryCode,
        public ?string $latitude,
        public ?string $longitude,
        public string $currencyCode,
        public string $timezone,
    ) {}

    /**
     * @throws InvalidBusinessCurrency
     * @throws InvalidBusinessTimezone
     * @throws InvalidBusinessCoordinates
     */
    public function validate(): void
    {
        $this->validateCurrencyCode();
        $this->validateTimezone();
        $this->validateCoordinates();
    }

    private function validateCurrencyCode(): void
    {
        CurrencyCode::fromString($this->currencyCode);
    }

    private function validateTimezone(): void
    {
        Timezone::fromString($this->timezone);
    }

    private function validateCoordinates(): void
    {
        if ($this->latitude === null && $this->longitude === null) {
            return;
        }

        if ($this->latitude === null || $this->longitude === null) {
            throw InvalidBusinessCoordinates::incomplete();
        }

        $this->validateLatitude($this->latitude);
        $this->validateLongitude($this->longitude);
    }

    private function validateLatitude(string $latitude): void
    {
        $degrees = self::degreesOf($latitude);

        if ($degrees < self::MINIMUM_LATITUDE || $degrees > self::MAXIMUM_LATITUDE) {
            throw InvalidBusinessCoordinates::latitudeOutOfRange($latitude);
        }
    }

    private function validateLongitude(string $longitude): void
    {
        $degrees = self::degreesOf($longitude);

        if ($degrees < self::MINIMUM_LONGITUDE || $degrees > self::MAXIMUM_LONGITUDE) {
            throw InvalidBusinessCoordinates::longitudeOutOfRange($longitude);
        }
    }

    private static function degreesOf(string $value): float
    {
        if (! is_numeric(trim($value))) {
            throw InvalidBusinessCoordinates::malformed($value);
        }

        return (float) trim($value);
    }
}
