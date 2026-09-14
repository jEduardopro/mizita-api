<?php

declare(strict_types=1);

namespace App\Shared\ValueObjects;

use InvalidArgumentException;

final class InvalidPhoneNumber extends InvalidArgumentException
{
    public static function empty(): self
    {
        return new self('A phone number cannot be empty.');
    }

    public static function malformed(string $nationalNumber): self
    {
        return new self("[{$nationalNumber}] is not a valid national phone number.");
    }

    public static function callingCodeMismatch(CountryCode $country, int $callingCode): self
    {
        return new self(
            "Calling code [+{$callingCode}] does not belong to country [{$country->value}], which dials [{$country->dialCode()}]."
        );
    }

    public static function inconsistentE164(string $e164, string $expected): self
    {
        return new self("E.164 form [{$e164}] does not match its parts, which compose [{$expected}].");
    }
}
