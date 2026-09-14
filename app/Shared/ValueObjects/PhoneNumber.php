<?php

declare(strict_types=1);

namespace App\Shared\ValueObjects;

final readonly class PhoneNumber
{
    private const MINIMUM_NATIONAL_DIGITS = 7;

    private const MAXIMUM_E164_DIGITS = 15;

    /**
     * @param  list<string>  $timezones
     */
    private function __construct(
        private CountryCode $country,
        private int $callingCode,
        private string $nationalNumber,
        private string $e164,
        private PhoneNumberType $type,
        private ?string $geoDescription,
        private array $timezones,
    ) {}

    /**
     * @param  list<string>  $timezones
     *
     * @throws InvalidPhoneNumber
     */
    public static function of(
        CountryCode $country,
        int $callingCode,
        string $nationalNumber,
        string $e164,
        PhoneNumberType $type,
        ?string $geoDescription,
        array $timezones,
    ): self {
        $digits = trim($nationalNumber);

        if ($digits === '') {
            throw InvalidPhoneNumber::empty();
        }

        if ($country->dialCode() !== '+'.$callingCode) {
            throw InvalidPhoneNumber::callingCodeMismatch($country, $callingCode);
        }

        if (preg_match(self::nationalNumberPattern($callingCode), $digits) !== 1) {
            throw InvalidPhoneNumber::malformed($nationalNumber);
        }

        $composed = '+'.$callingCode.$digits;

        if ($e164 !== $composed) {
            throw InvalidPhoneNumber::inconsistentE164($e164, $composed);
        }

        return new self(
            country: $country,
            callingCode: $callingCode,
            nationalNumber: $digits,
            e164: $e164,
            type: $type,
            geoDescription: self::descriptionOrNothing($geoDescription),
            timezones: array_values($timezones),
        );
    }

    public function country(): CountryCode
    {
        return $this->country;
    }

    public function callingCode(): int
    {
        return $this->callingCode;
    }

    public function nationalNumber(): string
    {
        return $this->nationalNumber;
    }

    public function e164(): string
    {
        return $this->e164;
    }

    public function type(): PhoneNumberType
    {
        return $this->type;
    }

    public function geoDescription(): ?string
    {
        return $this->geoDescription;
    }

    /**
     * @return list<string>
     */
    public function timezones(): array
    {
        return $this->timezones;
    }

    public function equals(self $other): bool
    {
        return $this->e164 === $other->e164;
    }

    private static function descriptionOrNothing(?string $geoDescription): ?string
    {
        $description = trim($geoDescription ?? '');

        return $description === '' ? null : $description;
    }

    private static function nationalNumberPattern(int $callingCode): string
    {
        $available = self::MAXIMUM_E164_DIGITS - strlen((string) $callingCode);

        return sprintf('/^\d{%d,%d}$/', self::MINIMUM_NATIONAL_DIGITS, $available);
    }
}
