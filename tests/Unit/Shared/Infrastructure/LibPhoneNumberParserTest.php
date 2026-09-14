<?php

declare(strict_types=1);

use App\Shared\Infrastructure\LibPhoneNumberParser;
use App\Shared\ValueObjects\CountryCode;
use App\Shared\ValueObjects\PhoneNumber;
use App\Shared\ValueObjects\PhoneNumberType;
use libphonenumber\PhoneNumberToTimeZonesMapper;
use libphonenumber\PhoneNumberUtil;

beforeEach(function () {
    $this->parser = new LibPhoneNumberParser;
});

describe('the defect this work exists to close', function () {
    it('refuses a Mexican number declared as a United States one', function () {
        expect($this->parser->parse(CountryCode::Us, '8421133471'))->toBeNull();
    });

    it('accepts the very same digits under the country they belong to', function () {
        $number = $this->parser->parse(CountryCode::Mx, '8421133471');

        expect($number)->toBeInstanceOf(PhoneNumber::class)
            ->and($number->country())->toBe(CountryCode::Mx)
            ->and($number->callingCode())->toBe(52)
            ->and($number->nationalNumber())->toBe('8421133471')
            ->and($number->e164())->toBe('+528421133471')
            ->and($number->type())->toBe(PhoneNumberType::FixedLineOrMobile)
            ->and($number->geoDescription())->toBe('Coahuila')
            ->and($number->timezones())->toBe(['America/Mexico_City']);
    });

    it('rejects a number that belongs to another platform country than the one declared', function () {
        expect($this->parser->parse(CountryCode::Us, '+528421133471'))->toBeNull();
    });
});

describe('a number the platform supports', function () {
    it('carries every fact the numbering plan publishes about it', function (
        CountryCode $country,
        string $typed,
        string $e164,
        PhoneNumberType $type,
        ?string $geoDescription,
        array $timezones,
    ) {
        $number = $this->parser->parse($country, $typed);

        expect($number)->not->toBeNull()
            ->and($number->e164())->toBe($e164)
            ->and($number->type())->toBe($type)
            ->and($number->geoDescription())->toBe($geoDescription)
            ->and($number->timezones())->toBe($timezones);
    })->with([
        'a Monterrey landline' => [
            CountryCode::Mx, '8121001069', '+528121001069',
            PhoneNumberType::FixedLineOrMobile, 'Monterrey, NL', ['America/Mexico_City'],
        ],
        'a Mexico City number' => [
            CountryCode::Mx, '5512345678', '+525512345678',
            PhoneNumberType::FixedLineOrMobile, 'Mexico City, FD', ['America/Mexico_City'],
        ],
        'a New Jersey number' => [
            CountryCode::Us, '2015550123', '+12015550123',
            PhoneNumberType::FixedLineOrMobile, 'New Jersey', ['America/New_York'],
        ],
        'a toll-free United States number, which no place can be read from' => [
            CountryCode::Us, '8002530000', '+18002530000',
            PhoneNumberType::TollFree, null, [
                'America/Adak', 'America/Anchorage', 'America/Anguilla', 'America/Antigua',
                'America/Barbados', 'America/Boise', 'America/Cayman', 'America/Chicago',
                'America/Denver', 'America/Dominica', 'America/Edmonton', 'America/Fort_Nelson',
                'America/Grand_Turk', 'America/Grenada', 'America/Halifax', 'America/Jamaica',
                'America/Juneau', 'America/Los_Angeles', 'America/Lower_Princes',
                'America/Montserrat', 'America/Nassau', 'America/New_York',
                'America/North_Dakota/Center', 'America/Phoenix', 'America/Port_of_Spain',
                'America/Puerto_Rico', 'America/Regina', 'America/Santo_Domingo',
                'America/St_Johns', 'America/St_Kitts', 'America/St_Lucia', 'America/St_Thomas',
                'America/St_Vincent', 'America/Toronto', 'America/Tortola', 'America/Vancouver',
                'America/Winnipeg', 'Atlantic/Bermuda', 'Pacific/Guam', 'Pacific/Honolulu',
                'Pacific/Pago_Pago', 'Pacific/Saipan',
            ],
        ],
    ]);

    it('reads the country and the national number back apart', function () {
        $number = $this->parser->parse(CountryCode::Us, '2015550123');

        expect($number->country())->toBe(CountryCode::Us)
            ->and($number->callingCode())->toBe(1)
            ->and($number->nationalNumber())->toBe('2015550123');
    });
});

describe('the platform whitelist on top of the library', function () {
    it('agrees with the library that a Spanish mobile is a real number', function () {
        $numbers = PhoneNumberUtil::getInstance();

        expect($numbers->isValidNumber($numbers->parse('+34600123456', 'MX')))->toBeTrue();
    });

    it('rejects it anyway, because the platform does not operate in Spain', function () {
        expect($this->parser->parse(CountryCode::Mx, '+34600123456'))->toBeNull()
            ->and($this->parser->parse(CountryCode::Us, '+34600123456'))->toBeNull();
    });
});

describe('what a person actually types', function () {
    it('tolerates the separators people write their own number with', function (string $typed) {
        expect($this->parser->parse(CountryCode::Mx, $typed)?->e164())->toBe('+525512345678');
    })->with([
        'bare digits' => '5512345678',
        'a space and a hyphen' => '55 1234-5678',
        'parentheses' => '(55) 1234 5678',
        'dots' => '55.1234.5678',
        'surrounding whitespace' => '   5512345678   ',
        'its own dial code' => '+52 55 1234 5678',
        'a dial code with no space' => '+525512345678',
    ]);

    it('returns nothing for a blank number instead of throwing', function (string $typed) {
        expect($this->parser->parse(CountryCode::Mx, $typed))->toBeNull();
    })->with([
        'empty' => '',
        'spaces' => '   ',
        'a tab' => "\t",
    ]);

    it('returns nothing for something that is not a number at all, instead of throwing', function (string $typed) {
        expect($this->parser->parse(CountryCode::Mx, $typed))->toBeNull();
    })->with([
        'letters' => 'call me maybe',
        'a single digit' => '1',
        'punctuation only' => '()-.',
        'a number far too long' => '5512345678901234567890',
        'a number too short for the country' => '551234',
    ]);
});

it('never lets the unknown-timezone sentinel reach a stored number', function (CountryCode $country, string $typed) {
    $timezones = $this->parser->parse($country, $typed)->timezones();

    expect($timezones)->not->toContain(PhoneNumberToTimeZonesMapper::UNKNOWN_TIMEZONE)
        ->and($timezones)->not->toBeEmpty();

    foreach ($timezones as $timezone) {
        expect(new DateTimeZone($timezone))->toBeInstanceOf(DateTimeZone::class);
    }
})->with([
    'a Mexican landline' => [CountryCode::Mx, '8121001069'],
    'a Mexican toll-free number' => [CountryCode::Mx, '8001234567'],
    'a United States landline' => [CountryCode::Us, '4155550100'],
    'a United States toll-free number' => [CountryCode::Us, '8002530000'],
]);

it('hands back a list, not a map, so the order survives being stored as json', function () {
    $timezones = $this->parser->parse(CountryCode::Us, '8002530000')->timezones();

    expect(array_keys($timezones))->toBe(range(0, count($timezones) - 1));
});

it('answers the same way every time it is asked', function () {
    $first = $this->parser->parse(CountryCode::Mx, '8121001069');
    $second = (new LibPhoneNumberParser)->parse(CountryCode::Mx, ' 81 2100 1069 ');

    expect($first->equals($second))->toBeTrue()
        ->and($first->geoDescription())->toBe($second->geoDescription())
        ->and($first->timezones())->toBe($second->timezones());
});
