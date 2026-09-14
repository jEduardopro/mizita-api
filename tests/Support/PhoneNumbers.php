<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Shared\ValueObjects\CountryCode;
use App\Shared\ValueObjects\PhoneNumber;
use App\Shared\ValueObjects\PhoneNumberType;

/**
 * The fixed phone numbers the unit suite uses, in one place.
 *
 * A PhoneNumber now carries seven facts, and six of them are metadata a parser
 * established rather than anything a test cares about. Spreading those literals
 * across every file that needs "a valid number" would mean seven lines of noise
 * per fixture and seven places to edit when one of them changes.
 *
 * Every default below is what libphonenumber actually returns for the number in
 * question, verified against the library, so a fixture is never a number the
 * platform would have rejected at the edge. Pass an argument only when the fact
 * itself is what a test is about - the defaults stop being true of the number
 * once its digits change.
 */
final class PhoneNumbers
{
    public const MX_NATIONAL_NUMBER = '5512345678';

    public const MX_E164 = '+525512345678';

    public const MX_GEO_DESCRIPTION = 'Mexico City, FD';

    public const MX_TIMEZONE = 'America/Mexico_City';

    public const US_NATIONAL_NUMBER = '2015550123';

    public const US_E164 = '+12015550123';

    public const US_GEO_DESCRIPTION = 'New Jersey';

    public const US_TIMEZONE = 'America/New_York';

    /**
     * @param  list<string>  $timezones
     */
    public static function mexican(
        string $nationalNumber = self::MX_NATIONAL_NUMBER,
        PhoneNumberType $type = PhoneNumberType::FixedLineOrMobile,
        ?string $geoDescription = self::MX_GEO_DESCRIPTION,
        array $timezones = [self::MX_TIMEZONE],
    ): PhoneNumber {
        return self::in(CountryCode::Mx, $nationalNumber, $type, $geoDescription, $timezones);
    }

    /**
     * @param  list<string>  $timezones
     */
    public static function american(
        string $nationalNumber = self::US_NATIONAL_NUMBER,
        PhoneNumberType $type = PhoneNumberType::FixedLineOrMobile,
        ?string $geoDescription = self::US_GEO_DESCRIPTION,
        array $timezones = [self::US_TIMEZONE],
    ): PhoneNumber {
        return self::in(CountryCode::Us, $nationalNumber, $type, $geoDescription, $timezones);
    }

    /**
     * The E.164 form is composed rather than passed, because PhoneNumber::of()
     * rejects a pair that disagrees - and a fixture whose parts contradict each
     * other is a test about that invariant, which writes its own literals.
     *
     * @param  list<string>  $timezones
     */
    public static function in(
        CountryCode $country,
        string $nationalNumber,
        PhoneNumberType $type = PhoneNumberType::FixedLineOrMobile,
        ?string $geoDescription = null,
        array $timezones = [],
    ): PhoneNumber {
        return PhoneNumber::of(
            country: $country,
            callingCode: (int) ltrim($country->dialCode(), '+'),
            nationalNumber: $nationalNumber,
            e164: $country->dialCode().$nationalNumber,
            type: $type,
            geoDescription: $geoDescription,
            timezones: $timezones,
        );
    }
}
