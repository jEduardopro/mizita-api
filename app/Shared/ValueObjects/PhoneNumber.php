<?php

declare(strict_types=1);

namespace App\Shared\ValueObjects;

/**
 * A phone number as the platform stores it: a country and the national number
 * within it, kept apart so the dial code is never guessed from the digits.
 *
 * Formatting is the caller's habit, not a fact about the number, so the
 * separators people type are removed on the way in. What survives is digits,
 * which is what makes two numbers comparable and an index on them useful.
 */
final readonly class PhoneNumber
{
    /** E.164 allows fifteen digits at most, dial code included; seven is the shortest national number in use. */
    private const MINIMUM_DIGITS = 7;

    private const MAXIMUM_DIGITS = 15;

    /** The separators people type: spaces, hyphens, parentheses and dots. */
    private const SEPARATORS = [' ', "\t", '-', '(', ')', '.'];

    private function __construct(
        private CountryCode $country,
        private string $nationalNumber,
    ) {}

    /**
     * @throws InvalidPhoneNumber when the national number is blank, or is
     *                            anything other than 7 to 15 digits once its separators are removed
     */
    public static function fromParts(CountryCode $country, string $nationalNumber): self
    {
        $digits = str_replace(self::SEPARATORS, '', trim($nationalNumber));

        if ($digits === '') {
            throw InvalidPhoneNumber::empty();
        }

        if (preg_match(self::digitsPattern(), $digits) !== 1) {
            throw InvalidPhoneNumber::malformed($nationalNumber);
        }

        return new self($country, $digits);
    }

    public function country(): CountryCode
    {
        return $this->country;
    }

    public function nationalNumber(): string
    {
        return $this->nationalNumber;
    }

    /** The dialable form: country calling code followed by the national number. */
    public function e164(): string
    {
        return $this->country->dialCode().$this->nationalNumber;
    }

    public function equals(self $other): bool
    {
        return $this->country === $other->country
            && $this->nationalNumber === $other->nationalNumber;
    }

    private static function digitsPattern(): string
    {
        return sprintf('/^\d{%d,%d}$/', self::MINIMUM_DIGITS, self::MAXIMUM_DIGITS);
    }
}
