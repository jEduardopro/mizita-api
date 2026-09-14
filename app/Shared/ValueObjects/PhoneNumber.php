<?php

declare(strict_types=1);

namespace App\Shared\ValueObjects;

/**
 * A phone number the platform has established to be real. One value object
 * rather than two, so there is no such thing as a half-validated number in the
 * system: establishing those facts needs metadata this object must not carry,
 * so it never parses anything. PhoneNumberParser assembles it, and it only
 * checks that the parts it was handed agree with each other.
 *
 * The geographic description is a raw label in a fixed 'en' locale ("Coahuila"),
 * a coarse fact about the number rather than display copy, and must not be shown
 * to a user as if it were translated.
 */
final readonly class PhoneNumber
{
    /** The shortest national number in use anywhere. */
    private const MINIMUM_NATIONAL_DIGITS = 7;

    /** E.164 allows fifteen digits in total, the country calling code included. */
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
     * @param  list<string>  $timezones  IANA identifiers, never the library's
     *                                   'Etc/Unknown' sentinel
     *
     * @throws InvalidPhoneNumber when the parts are blank, out of range, or
     *                            disagree with each other
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

        // The country is the authority on its own calling code, so a pair that
        // disagrees means one of the two was carried over from another number.
        if ($country->dialCode() !== '+'.$callingCode) {
            throw InvalidPhoneNumber::callingCodeMismatch($country, $callingCode);
        }

        if (preg_match(self::nationalNumberPattern($callingCode), $digits) !== 1) {
            throw InvalidPhoneNumber::malformed($nationalNumber);
        }

        // The guard that stops a mapper bug writing a mismatched pair: the
        // E.164 form is the sum of the parts, so any other value is a number
        // that would be dialled differently from the one that was stored.
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

    /** Sign excluded: 52, not "+52". */
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

    /** Null for a number no place can be read from. */
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

    /** Two numbers are the same number when they are dialled the same way; the metadata facts are derived. */
    public function equals(self $other): bool
    {
        return $this->e164 === $other->e164;
    }

    private static function descriptionOrNothing(?string $geoDescription): ?string
    {
        $description = trim($geoDescription ?? '');

        return $description === '' ? null : $description;
    }

    /** E.164's total budget minus the calling code's digits, so a long country code narrows it. */
    private static function nationalNumberPattern(int $callingCode): string
    {
        $available = self::MAXIMUM_E164_DIGITS - strlen((string) $callingCode);

        return sprintf('/^\d{%d,%d}$/', self::MINIMUM_NATIONAL_DIGITS, $available);
    }
}
