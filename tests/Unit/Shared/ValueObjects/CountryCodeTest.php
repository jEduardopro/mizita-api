<?php

declare(strict_types=1);

use App\Shared\ValueObjects\CountryCode;

it('backs each country with the ISO 3166-1 alpha-2 code that is stored', function (CountryCode $country, string $stored) {
    expect($country->value)->toBe($stored);
})->with([
    'Mexico' => [CountryCode::Mx, 'MX'],
    'United States' => [CountryCode::Us, 'US'],
]);

it('knows the E.164 calling code of every country it supports', function (CountryCode $country, string $dialCode) {
    expect($country->dialCode())->toBe($dialCode);
})->with([
    'Mexico' => [CountryCode::Mx, '+52'],
    'United States' => [CountryCode::Us, '+1'],
]);

it('exposes exactly the countries the platform operates in', function () {
    expect(array_column(CountryCode::cases(), 'value'))->toBe(['MX', 'US']);
});

it('resolves a stored value back to its case', function () {
    expect(CountryCode::from('MX'))->toBe(CountryCode::Mx)
        ->and(CountryCode::from('US'))->toBe(CountryCode::Us);
});

it('refuses a value it does not know', function (string $value) {
    expect(CountryCode::tryFrom($value))->toBeNull();
})->with([
    'unsupported country' => 'ES',
    'wrong case' => 'mx',
    'padded' => ' MX ',
    'alpha-3' => 'MEX',
    'a dial code' => '+52',
    'empty' => '',
]);
