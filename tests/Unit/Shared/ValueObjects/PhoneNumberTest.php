<?php

declare(strict_types=1);

use App\Shared\ValueObjects\CountryCode;
use App\Shared\ValueObjects\InvalidPhoneNumber;
use App\Shared\ValueObjects\PhoneNumber;
use App\Shared\ValueObjects\PhoneNumberType;
use Tests\Support\PhoneNumbers;

it('carries every fact it was handed', function () {
    $number = PhoneNumber::of(
        country: CountryCode::Mx,
        callingCode: 52,
        nationalNumber: '8121001069',
        e164: '+528121001069',
        type: PhoneNumberType::FixedLineOrMobile,
        geoDescription: 'Monterrey, NL',
        timezones: ['America/Mexico_City'],
    );

    expect($number->country())->toBe(CountryCode::Mx)
        ->and($number->callingCode())->toBe(52)
        ->and($number->nationalNumber())->toBe('8121001069')
        ->and($number->e164())->toBe('+528121001069')
        ->and($number->type())->toBe(PhoneNumberType::FixedLineOrMobile)
        ->and($number->geoDescription())->toBe('Monterrey, NL')
        ->and($number->timezones())->toBe(['America/Mexico_City']);
});

it('no longer offers the two-part constructor that guessed the rest', function () {
    expect(method_exists(PhoneNumber::class, 'fromParts'))->toBeFalse();
});

it('is built through of() and no other way', function () {
    expect((new ReflectionClass(PhoneNumber::class))->getConstructor()->isPrivate())->toBeTrue();
});

describe('the national number', function () {
    it('is trimmed of the whitespace around it', function () {
        expect(PhoneNumber::of(
            CountryCode::Mx, 52, '  5512345678  ', '+525512345678',
            PhoneNumberType::Mobile, null, [],
        )->nationalNumber())->toBe('5512345678');
    });

    it('rejects a blank one', function (string $nationalNumber) {
        expect(fn () => PhoneNumber::of(
            CountryCode::Mx, 52, $nationalNumber, '+52', PhoneNumberType::Mobile, null, [],
        ))->toThrow(InvalidPhoneNumber::class, 'A phone number cannot be empty.');
    })->with([
        'empty' => '',
        'spaces' => '   ',
        'a tab' => "\t",
        'a newline' => "\n",
    ]);

    it('rejects anything that is not a run of digits', function (string $nationalNumber) {
        expect(fn () => PhoneNumber::of(
            CountryCode::Mx, 52, $nationalNumber, '+52'.$nationalNumber,
            PhoneNumberType::Mobile, null, [],
        ))->toThrow(InvalidPhoneNumber::class);
    })->with([
        'letters' => '55CALLNOW',
        'letters mixed in' => '551234x678',
        'spaces' => '55 1234 5678',
        'hyphens' => '55-1234-5678',
        'parentheses' => '(55)12345678',
        'dots' => '55.1234.5678',
        'a leading plus' => '+525512345678',
        'unicode digits' => '٥٥١٢٣٤٥٦٧٨',
    ]);

    it('names the number it could not build', function () {
        expect(fn () => PhoneNumber::of(
            CountryCode::Mx, 52, '55CALLNOW', '+5255CALLNOW', PhoneNumberType::Mobile, null, [],
        ))->toThrow(InvalidPhoneNumber::class, '[55CALLNOW] is not a valid national phone number.');
    });

    it('accepts a number at each end of what E.164 leaves for it', function (
        CountryCode $country,
        int $callingCode,
        string $nationalNumber,
    ) {
        expect(PhoneNumber::of(
            $country, $callingCode, $nationalNumber, '+'.$callingCode.$nationalNumber,
            PhoneNumberType::FixedLine, null, [],
        )->nationalNumber())->toBe($nationalNumber);
    })->with([
        'the shortest national number in use anywhere' => [CountryCode::Mx, 52, '1234567'],
        'the longest a +52 number can be' => [CountryCode::Mx, 52, '1234567890123'],
        'the longest a +1 number can be' => [CountryCode::Us, 1, '12345678901234'],
    ]);

    it('rejects a number one digit past either end', function (
        CountryCode $country,
        int $callingCode,
        string $nationalNumber,
    ) {
        expect(fn () => PhoneNumber::of(
            $country, $callingCode, $nationalNumber, '+'.$callingCode.$nationalNumber,
            PhoneNumberType::FixedLine, null, [],
        ))->toThrow(InvalidPhoneNumber::class);
    })->with([
        'one digit short' => [CountryCode::Mx, 52, '123456'],
        'one digit past what +52 leaves' => [CountryCode::Mx, 52, '12345678901234'],
        'one digit past what +1 leaves' => [CountryCode::Us, 1, '123456789012345'],
    ]);
});

describe('the calling code', function () {
    it('must be the one the declared country dials', function () {
        expect(fn () => PhoneNumber::of(
            CountryCode::Mx, 1, '2015550123', '+12015550123', PhoneNumberType::Mobile, null, [],
        ))->toThrow(
            InvalidPhoneNumber::class,
            'Calling code [+1] does not belong to country [MX], which dials [+52].',
        );
    });

    it('rejects a calling code belonging to nowhere the platform operates', function () {
        expect(fn () => PhoneNumber::of(
            CountryCode::Mx, 34, '600123456', '+34600123456', PhoneNumberType::Mobile, null, [],
        ))->toThrow(InvalidPhoneNumber::class);
    });

    it('is read back as a number, sign excluded', function () {
        expect(PhoneNumbers::mexican()->callingCode())->toBe(52)
            ->and(PhoneNumbers::american()->callingCode())->toBe(1);
    });
});

describe('the dialable form', function () {
    it('must be the sum of the parts it is stored beside', function () {
        expect(fn () => PhoneNumber::of(
            CountryCode::Mx, 52, '5512345678', '+525599999999', PhoneNumberType::Mobile, null, [],
        ))->toThrow(
            InvalidPhoneNumber::class,
            'E.164 form [+525599999999] does not match its parts, which compose [+525512345678].',
        );
    });

    it('rejects a form that merely looks the same', function (string $e164) {
        expect(fn () => PhoneNumber::of(
            CountryCode::Mx, 52, '5512345678', $e164, PhoneNumberType::Mobile, null, [],
        ))->toThrow(InvalidPhoneNumber::class);
    })->with([
        'no plus sign' => '525512345678',
        'formatted' => '+52 55 1234 5678',
        'the national number alone' => '5512345678',
        'trailing whitespace' => '+525512345678 ',
        'empty' => '',
    ]);

    it('is composed from the trimmed national number, not the raw one', function () {
        expect(PhoneNumber::of(
            CountryCode::Mx, 52, '  5512345678  ', '+525512345678',
            PhoneNumberType::Mobile, null, [],
        )->e164())->toBe('+525512345678');
    });
});

describe('the geographic description', function () {
    it('keeps a real label exactly as the metadata phrased it', function () {
        expect(PhoneNumbers::mexican()->geoDescription())->toBe('Mexico City, FD');
    });

    it('is nothing at all when there is no place to name', function (?string $geoDescription) {
        expect(PhoneNumbers::mexican(geoDescription: $geoDescription)->geoDescription())->toBeNull();
    })->with([
        'null' => null,
        'empty' => '',
        'spaces' => '   ',
        'a tab' => "\t",
    ]);

    it('is trimmed rather than stored with the whitespace around it', function () {
        expect(PhoneNumbers::mexican(geoDescription: '  Coahuila  ')->geoDescription())->toBe('Coahuila');
    });

    it('keeps accents and punctuation', function () {
        expect(PhoneNumbers::mexican(geoDescription: 'Michoacán de Ocampo')->geoDescription())
            ->toBe('Michoacán de Ocampo');
    });
});

describe('the time zones', function () {
    it('keeps them in the order they arrived', function () {
        expect(PhoneNumbers::in(
            CountryCode::Us, '2015550123',
            timezones: ['America/New_York', 'America/Chicago', 'America/Denver'],
        )->timezones())->toBe(['America/New_York', 'America/Chicago', 'America/Denver']);
    });

    it('accepts a number no zone could be read from', function () {
        expect(PhoneNumbers::mexican(timezones: [])->timezones())->toBe([]);
    });

    it('re-indexes them into a list, so json stores an array and not an object', function () {
        $gapped = [1 => 'Europe/Madrid', 3 => 'Atlantic/Canary'];

        expect(PhoneNumbers::mexican(timezones: array_values($gapped))->timezones())
            ->toBe(['Europe/Madrid', 'Atlantic/Canary'])
            ->and(PhoneNumbers::mexican(timezones: $gapped)->timezones())
            ->toBe(['Europe/Madrid', 'Atlantic/Canary']);
    });
});

describe('equality', function () {
    it('is decided by how the number is dialled and by nothing else', function () {
        $number = PhoneNumbers::mexican();

        expect($number->equals($number))->toBeTrue()
            ->and($number->equals(PhoneNumbers::mexican(
                type: PhoneNumberType::Mobile,
                geoDescription: null,
                timezones: [],
            )))->toBeTrue();
    });

    it('separates two numbers whenever either half of the dialable form differs', function () {
        expect(PhoneNumbers::mexican()->equals(PhoneNumbers::mexican('5512345679')))->toBeFalse()
            ->and(PhoneNumbers::mexican()->equals(PhoneNumbers::american('5512345678')))->toBeFalse();
    });
});
