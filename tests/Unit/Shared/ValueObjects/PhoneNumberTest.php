<?php

declare(strict_types=1);

use App\Shared\ValueObjects\CountryCode;
use App\Shared\ValueObjects\InvalidPhoneNumber;
use App\Shared\ValueObjects\PhoneNumber;

/*
| Plain PHP: a value object needs no container, no clock and no database.
|
| The rules under test are the ones the rest of the platform then stops
| re-checking - the entity, the mapper and the repository all take a
| PhoneNumber as already-valid - so this file is where "what a number may look
| like" is actually pinned down.
*/

it('keeps the country and the national number apart', function () {
    $number = PhoneNumber::fromParts(CountryCode::Mx, '5512345678');

    expect($number->country())->toBe(CountryCode::Mx)
        ->and($number->nationalNumber())->toBe('5512345678');
});

it('strips the separators a person types', function (string $typed, string $stored) {
    expect(PhoneNumber::fromParts(CountryCode::Mx, $typed)->nationalNumber())->toBe($stored);
})->with([
    'realistic messy input' => [' (55) 1234-5678 ', '5512345678'],
    'spaces' => ['55 1234 5678', '5512345678'],
    'tabs' => ["55\t1234\t5678", '5512345678'],
    'hyphens' => ['55-1234-5678', '5512345678'],
    'parentheses' => ['(55)12345678', '5512345678'],
    'dots' => ['55.1234.5678', '5512345678'],
    'surrounding whitespace only' => ['   5512345678   ', '5512345678'],
]);

it('accepts a national number at each end of the allowed length', function (string $nationalNumber) {
    expect(PhoneNumber::fromParts(CountryCode::Us, $nationalNumber)->nationalNumber())
        ->toBe($nationalNumber);
})->with([
    'shortest allowed: 7 digits' => str_repeat('1', 7),
    'longest allowed: 15 digits' => str_repeat('1', 15),
]);

it('rejects a national number outside the allowed length', function (string $nationalNumber) {
    expect(fn () => PhoneNumber::fromParts(CountryCode::Us, $nationalNumber))
        ->toThrow(InvalidPhoneNumber::class);
})->with([
    'one digit short: 6 digits' => str_repeat('1', 6),
    'one digit long: 16 digits' => str_repeat('1', 16),
]);

it('counts the length after the separators are gone, not before', function () {
    // Six digits dressed up as ten characters is still six digits.
    expect(fn () => PhoneNumber::fromParts(CountryCode::Mx, '12-34-56'))
        ->toThrow(InvalidPhoneNumber::class);
});

it('rejects anything that is not a digit once the separators are gone', function (string $nationalNumber) {
    expect(fn () => PhoneNumber::fromParts(CountryCode::Mx, $nationalNumber))
        ->toThrow(InvalidPhoneNumber::class);
})->with([
    'letters' => ['55CALLNOW'],
    'letters mixed in' => ['551234x678'],
    // The dial code belongs to the CountryCode, so a number carrying its own
    // prefix is ambiguous rather than merely oddly formatted.
    'a leading plus' => ['+525512345678'],
    'a leading plus after trimming' => [' +525512345678 '],
    'a slash' => ['55/1234/5678'],
    'an underscore' => ['55_1234_5678'],
    'unicode digits' => ['٥٥١٢٣٤٥٦٧٨'],
]);

it('rejects a blank national number', function (string $nationalNumber) {
    expect(fn () => PhoneNumber::fromParts(CountryCode::Mx, $nationalNumber))
        ->toThrow(InvalidPhoneNumber::class, 'A phone number cannot be empty.');
})->with([
    'empty' => [''],
    'spaces' => ['   '],
    'tab' => ["\t"],
    'separators only' => ['()-. '],
]);

it('names the number it could not build', function () {
    expect(fn () => PhoneNumber::fromParts(CountryCode::Mx, '55CALLNOW'))
        ->toThrow(InvalidPhoneNumber::class, '[55CALLNOW] is not a valid national phone number.');
});

it('composes the dialable form from the country and the national number', function () {
    expect(PhoneNumber::fromParts(CountryCode::Mx, ' (55) 1234-5678 ')->e164())->toBe('+525512345678')
        ->and(PhoneNumber::fromParts(CountryCode::Us, '(415) 555-0100')->e164())->toBe('+14155550100');
});

it('treats two numbers as equal when the country and the digits match', function () {
    $number = PhoneNumber::fromParts(CountryCode::Mx, '5512345678');

    expect($number->equals(PhoneNumber::fromParts(CountryCode::Mx, '5512345678')))->toBeTrue()
        // Formatting is the caller's habit, not a fact about the number.
        ->and($number->equals(PhoneNumber::fromParts(CountryCode::Mx, ' (55) 1234-5678 ')))->toBeTrue()
        ->and($number->equals($number))->toBeTrue();
});

it('treats two numbers as different when either half differs', function () {
    $number = PhoneNumber::fromParts(CountryCode::Mx, '5512345678');

    expect($number->equals(PhoneNumber::fromParts(CountryCode::Us, '5512345678')))->toBeFalse()
        ->and($number->equals(PhoneNumber::fromParts(CountryCode::Mx, '5512345679')))->toBeFalse();
});
