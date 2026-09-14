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

final class LibPhoneNumberParser implements PhoneNumberParser
{
    private const GEOCODING_LOCALE = 'en';

    private readonly PhoneNumberUtil $numbers;

    private readonly PhoneNumberOfflineGeocoder $geocoder;

    private readonly PhoneNumberToTimeZonesMapper $timezones;

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

    private function read(string $number, CountryCode $country): ?LibPhoneNumber
    {
        if ($number === '') {
            return null;
        }

        try {
            return $this->numbers->parse($number, $country->value);
        } catch (NumberParseException) {
        }

        try {
            return $this->numbers->parse($country->dialCode().$number, $country->value);
        } catch (NumberParseException) {
            return null;
        }
    }

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
        return array_values(array_filter(
            $this->timezones->getTimeZonesForNumber($parsed),
            static fn (string $timezone): bool => $timezone !== PhoneNumberToTimeZonesMapper::UNKNOWN_TIMEZONE,
        ));
    }
}
