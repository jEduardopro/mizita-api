<?php

declare(strict_types=1);

use App\Shared\Infrastructure\LibPhoneNumberParser;
use App\Shared\ValueObjects\CountryCode;
use App\Shared\ValueObjects\PhoneNumber;
use App\Shared\ValueObjects\PhoneNumberType;
use libphonenumber\PhoneNumberToTimeZonesMapper;
use libphonenumber\PhoneNumberUtil;

/*
| The one test in the suite that deliberately uses the real library.
|
| Everything else about phones is asserted against the port, which is the point
| of the port - but a port is only worth having if one test somewhere proves the
| adapter behind it tells the truth. This is that test, and it is also the one
| that closes the reported defect: a Mexican number typed under the United States
| flag was stored as a US number, because nothing ever asked whether it was real.
|
| No container: the adapter takes no constructor arguments, because the library
| exposes its three helpers only as singletons. `new LibPhoneNumberParser` is the
| whole arrangement.
|
| The expectations below are the library's own answers, read off the metadata
| rather than guessed. When one of them changes, it changed because the numbering
| plan did, and this file is where that should surface.
*/

beforeEach(function () {
    $this->parser = new LibPhoneNumberParser;
});

describe('the defect this work exists to close', function () {
    it('refuses a Mexican number declared as a United States one', function () {
        // 842 113 3471 is a real number in Coahuila and is not a number anywhere
        // in the North American plan. Before the parser, it was stored as US.
        expect($this->parser->parse(CountryCode::Us, '8421133471'))->toBeNull();
    });

    it('accepts the very same digits under the country they belong to', function () {
        $number = $this->parser->parse(CountryCode::Mx, '8421133471');

        expect($number)->toBeInstanceOf(PhoneNumber::class)
            ->and($number->country())->toBe(CountryCode::Mx)
            ->and($number->callingCode())->toBe(52)
            ->and($number->nationalNumber())->toBe('8421133471')
            ->and($number->e164())->toBe('+528421133471')
            // Coahuila, not Tamaulipas: 52842 is a Coahuila prefix. And both a
            // fixed line and a mobile, because the MX metadata publishes that
            // range under each - a number is not a mobile just because a person
            // carries it.
            ->and($number->type())->toBe(PhoneNumberType::FixedLineOrMobile)
            ->and($number->geoDescription())->toBe('Coahuila')
            ->and($number->timezones())->toBe(['America/Mexico_City']);
    });

    it('rejects a number that belongs to another platform country than the one declared', function () {
        // Typed with its own dial code, so the library reads it as Mexican while
        // the form says United States. The two disagree and that ends it.
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
        // The sibling assertion, and the reason this test is worth writing: it
        // pins down that the rejection below is the platform's doing and not the
        // library failing to recognise the number.
        $numbers = PhoneNumberUtil::getInstance();

        expect($numbers->isValidNumber($numbers->parse('+34600123456', 'MX')))->toBeTrue();
    });

    it('rejects it anyway, because the platform does not operate in Spain', function () {
        // How it actually arrives: the country box only offers the countries the
        // platform supports, so an unsupported one can only be typed into the
        // number box, dial code and all.
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
        // The rule calls this on whatever was submitted, so an empty box must be
        // an ordinary null - the 'required' rule owns that rejection.
        expect($this->parser->parse(CountryCode::Mx, $typed))->toBeNull();
    })->with([
        'empty' => '',
        'spaces' => '   ',
        'a tab' => "\t",
    ]);

    it('returns nothing for something that is not a number at all, instead of throwing', function (string $typed) {
        // Both parse attempts raise NumberParseException here, so this is the
        // test that the adapter catches the second one as well as the first.
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
    // 'Etc/Unknown' is what the mapper answers with rather than an empty list,
    // and it is not a zone anything can convert an appointment with. Every zone
    // that survives has to be one PHP can actually load.
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
    // No clock, no randomness, no state: two parses of one number are one fact.
    $first = $this->parser->parse(CountryCode::Mx, '8121001069');
    $second = (new LibPhoneNumberParser)->parse(CountryCode::Mx, ' 81 2100 1069 ');

    expect($first->equals($second))->toBeTrue()
        ->and($first->geoDescription())->toBe($second->geoDescription())
        ->and($first->timezones())->toBe($second->timezones());
});
