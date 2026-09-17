<?php

declare(strict_types=1);

use App\Domains\Customers\Application\Services\SubmittedPhoneNumber;
use App\Domains\Customers\Exceptions\InvalidCustomerPhone;
use App\Shared\ValueObjects\CountryCode;
use Tests\Support\Customers\CustomerFixtures;
use Tests\Support\FakePhoneNumberParser;
use Tests\Support\PhoneNumbers;

beforeEach(function () {
    $this->parser = FakePhoneNumberParser::accepting(PhoneNumbers::mexican());
    $this->submitted = new SubmittedPhoneNumber($this->parser);
});

it('answers with nothing when no phone was submitted', function () {
    expect($this->submitted->parse(null))->toBeNull()
        ->and($this->parser->wasConsulted())->toBeFalse();
});

it('answers with the number the parser recognised', function () {
    $number = $this->submitted->parse(CustomerFixtures::phoneInput());

    expect($number?->e164())->toBe(PhoneNumbers::MX_E164)
        ->and($number?->country())->toBe(CountryCode::Mx);
});

it('hands the parser the country and the digits as they were typed', function () {
    $this->submitted->parse(CustomerFixtures::phoneInput());

    expect($this->parser->calls())->toBe([[
        'country' => CountryCode::Mx,
        'nationalNumber' => PhoneNumbers::MX_NATIONAL_NUMBER,
    ]]);
});

it('reads a country code whatever case and padding it arrives in', function (string $countryCode) {
    expect($this->submitted->parse(CustomerFixtures::phoneInput(countryCode: $countryCode))?->e164())
        ->toBe(PhoneNumbers::MX_E164);
})->with([
    'lowercase' => 'mx',
    'mixed case' => 'mX',
    'padded' => '  MX  ',
]);

it('refuses a country the platform does not know, without asking the parser', function (string $countryCode) {
    expect(fn () => $this->submitted->parse(CustomerFixtures::phoneInput(countryCode: $countryCode)))
        ->toThrow(InvalidCustomerPhone::class)
        ->and($this->parser->wasConsulted())->toBeFalse();
})->with([
    'a country that does not exist' => 'ZZ',
    'an empty country' => '',
    'whitespace' => '   ',
    'a dial code instead of a country' => '+52',
]);

it('refuses a number the parser cannot read', function () {
    expect(fn () => $this->submitted->parse(CustomerFixtures::phoneInput(nationalNumber: '1')))
        ->toThrow(InvalidCustomerPhone::class)
        ->and($this->parser->calls())->toHaveCount(1);
});

it('refuses a number of a country the parser was not told about', function () {
    expect(fn () => $this->submitted->parse(CustomerFixtures::phoneInput(
        countryCode: 'US',
        nationalNumber: PhoneNumbers::US_NATIONAL_NUMBER,
    )))->toThrow(InvalidCustomerPhone::class);
});
