<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure;

use App\Shared\Contracts\PhoneNumberParser;
use App\Shared\ValueObjects\CountryCode;
use App\Shared\ValueObjects\PhoneNumber;
use App\Shared\ValueObjects\PhoneNumberType;
use libphonenumber\geocoding\PhoneNumberOfflineGeocoder;
use libphonenumber\NumberParseException;
use libphonenumber\PhoneNumber as LibPhoneNumber;
use libphonenumber\PhoneNumberFormat;
use libphonenumber\PhoneNumberToTimeZonesMapper;
use libphonenumber\PhoneNumberType as LibPhoneNumberType;
use libphonenumber\PhoneNumberUtil;

/**
 * The only class in the codebase allowed to import libphonenumber.
 *
 * Two checks stack here and answer different questions: the library says whether
 * a number is real anywhere, CountryCode says whether the platform operates
 * there. A Spanish mobile passes the first and fails the second, and both
 * rejections are a null, because to the person filling in the form they mean the
 * same thing.
 *
 * The library's type enum is mapped in a total match below rather than on
 * PhoneNumberType, because a shared value object is imported by every domain's
 * entities and whatever it reaches for is reached by all of them. An upgrade
 * that adds a type therefore fails here, with an UnhandledMatchError on the
 * first parse.
 */
final class LibPhoneNumberParser implements PhoneNumberParser
{
    /**
     * One fixed locale on purpose: what the geocoder returns is stored as a
     * coarse geographic fact, not display copy, so it must not vary with whoever
     * happened to submit the form.
     */
    private const GEOCODING_LOCALE = 'en';

    private readonly PhoneNumberUtil $numbers;

    private readonly PhoneNumberOfflineGeocoder $geocoder;

    private readonly PhoneNumberToTimeZonesMapper $timezones;

    /**
     * The library exposes its three helpers only as singletons, each loading a
     * slab of metadata on first use. They are resolved here rather than injected
     * because there is no container binding to inject - and this class is itself
     * bound as a singleton, so the cost is paid once per process.
     */
    public function __construct()
    {
        $this->numbers = PhoneNumberUtil::getInstance();
        $this->geocoder = PhoneNumberOfflineGeocoder::getInstance();
        $this->timezones = PhoneNumberToTimeZonesMapper::getInstance();
    }

    public function parse(CountryCode $country, string $nationalNumber): ?PhoneNumber
    {
        $parsed = $this->read(trim($nationalNumber), $country);

        if ($parsed === null || ! $this->numbers->isValidNumber($parsed)) {
            return null;
        }

        // Where the number actually belongs, which is not necessarily where the
        // caller said it does: a US-declared +52 number reaches this line, and
        // this is where it stops.
        if ($this->regionOf($parsed) !== $country) {
            return null;
        }

        if (! $this->numbers->isValidNumberForRegion($parsed, $country->value)) {
            return null;
        }

        $callingCode = $parsed->getCountryCode();

        if ($callingCode === null) {
            return null;
        }

        return PhoneNumber::of(
            country: $country,
            callingCode: $callingCode,
            nationalNumber: $this->numbers->getNationalSignificantNumber($parsed),
            e164: $this->numbers->format($parsed, PhoneNumberFormat::E164),
            type: $this->typeOf($parsed),
            geoDescription: $this->describe($parsed),
            timezones: $this->timezonesOf($parsed),
        );
    }

    /**
     * People type their own number as they say it, so the same digits arrive
     * bare, spaced, hyphenated, or already carrying the dial code. The second
     * attempt covers the one shape the region hint cannot rescue: a number
     * written with a leading international prefix the library does not
     * recognise on its own.
     */
    private function read(string $number, CountryCode $country): ?LibPhoneNumber
    {
        if ($number === '') {
            return null;
        }

        try {
            return $this->numbers->parse($number, $country->value);
        } catch (NumberParseException) {
            // Fall through to the retry.
        }

        try {
            return $this->numbers->parse($country->dialCode().$number, $country->value);
        } catch (NumberParseException) {
            return null;
        }
    }

    /** The platform country the number belongs to, or null for anywhere else. */
    private function regionOf(LibPhoneNumber $parsed): ?CountryCode
    {
        $region = $this->numbers->getRegionCodeForNumber($parsed);

        if ($region === null) {
            return null;
        }

        return CountryCode::tryFrom($region);
    }

    private function typeOf(LibPhoneNumber $parsed): PhoneNumberType
    {
        return match ($this->numbers->getNumberType($parsed)) {
            LibPhoneNumberType::FIXED_LINE => PhoneNumberType::FixedLine,
            LibPhoneNumberType::MOBILE => PhoneNumberType::Mobile,
            LibPhoneNumberType::FIXED_LINE_OR_MOBILE => PhoneNumberType::FixedLineOrMobile,
            LibPhoneNumberType::TOLL_FREE => PhoneNumberType::TollFree,
            LibPhoneNumberType::PREMIUM_RATE => PhoneNumberType::PremiumRate,
            LibPhoneNumberType::SHARED_COST => PhoneNumberType::SharedCost,
            LibPhoneNumberType::VOIP => PhoneNumberType::Voip,
            LibPhoneNumberType::PERSONAL_NUMBER => PhoneNumberType::PersonalNumber,
            LibPhoneNumberType::PAGER => PhoneNumberType::Pager,
            LibPhoneNumberType::UAN => PhoneNumberType::Uan,
            LibPhoneNumberType::UNKNOWN => PhoneNumberType::Unknown,
            LibPhoneNumberType::EMERGENCY => PhoneNumberType::Emergency,
            LibPhoneNumberType::VOICEMAIL => PhoneNumberType::Voicemail,
            LibPhoneNumberType::SHORT_CODE => PhoneNumberType::ShortCode,
            LibPhoneNumberType::STANDARD_RATE => PhoneNumberType::StandardRate,
        };
    }

    private function describe(LibPhoneNumber $parsed): ?string
    {
        $description = trim($this->geocoder->getDescriptionForNumber($parsed, self::GEOCODING_LOCALE));

        return $description === '' ? null : $description;
    }

    /**
     * @return list<string>
     */
    private function timezonesOf(LibPhoneNumber $parsed): array
    {
        // The mapper answers with a one-element sentinel rather than an empty
        // list when it cannot place a number, and 'Etc/Unknown' is not a zone
        // anything can convert with - so it never reaches the column.
        return array_values(array_filter(
            $this->timezones->getTimeZonesForNumber($parsed),
            static fn (string $timezone): bool => $timezone !== PhoneNumberToTimeZonesMapper::UNKNOWN_TIMEZONE,
        ));
    }
}
