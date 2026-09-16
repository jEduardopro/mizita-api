<?php

declare(strict_types=1);

namespace App\Domains\Addresses\Application\Dtos;

use App\Domains\Addresses\Exceptions\UnsupportedCountry;
use App\Shared\ValueObjects\CountryCode;

final readonly class ListStatesInput
{
    private const DEFAULT_COUNTRY = CountryCode::Mx;

    public function __construct(
        public string $countryCode,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromRequest(array $payload): self
    {
        $country = mb_strtoupper(trim(self::textOrEmpty($payload['country'] ?? null)));

        return new self($country === '' ? self::DEFAULT_COUNTRY->value : $country);
    }

    /**
     * @throws UnsupportedCountry
     */
    public function validate(): void
    {
        $this->validateCountryCode();
    }

    public function country(): CountryCode
    {
        return CountryCode::from($this->countryCode);
    }

    private function validateCountryCode(): void
    {
        if (CountryCode::tryFrom($this->countryCode) === null) {
            throw UnsupportedCountry::withCode($this->countryCode);
        }
    }

    private static function textOrEmpty(mixed $value): string
    {
        return is_string($value) ? $value : '';
    }
}
