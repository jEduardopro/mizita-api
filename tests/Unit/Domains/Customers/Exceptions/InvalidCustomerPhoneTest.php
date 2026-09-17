<?php

declare(strict_types=1);

use App\Domains\Customers\Exceptions\InvalidCustomerPhone;
use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\CountryCode;
use App\Shared\ValueObjects\DomainFailureKind;

it('tells a country it cannot dial apart from a number it cannot dial there', function () {
    expect(InvalidCustomerPhone::inCountry('XX')->getMessage())
        ->toBe('[XX] is not a country we can dial.')
        ->and(InvalidCustomerPhone::malformed('MX')->getMessage())
        ->toBe('The number offered cannot be dialled in [MX].');
});

it('says the same thing whether the country arrived as a string or as a value object', function () {
    expect(InvalidCustomerPhone::forCountry(CountryCode::Mx)->getMessage())
        ->toBe(InvalidCustomerPhone::malformed('MX')->getMessage());
});

it('answers with one error code however the number fell short', function (InvalidCustomerPhone $failure) {
    expect($failure->errorCode())->toBe('invalid_customer_phone');
})->with([
    'an undialable country' => fn () => InvalidCustomerPhone::inCountry('XX'),
    'a malformed number' => fn () => InvalidCustomerPhone::malformed('MX'),
    'a malformed number in a known country' => fn () => InvalidCustomerPhone::forCountry(CountryCode::Mx),
]);

it('classifies every way as a 422 rather than a conflict or a 500', function (InvalidCustomerPhone $failure) {
    expect($failure->kind())->toBe(DomainFailureKind::Invalid);
})->with([
    'an undialable country' => fn () => InvalidCustomerPhone::inCountry('XX'),
    'a malformed number' => fn () => InvalidCustomerPhone::malformed('MX'),
    'a malformed number in a known country' => fn () => InvalidCustomerPhone::forCountry(CountryCode::Mx),
]);

it('never quotes the number it refused, only the country it could not dial it in', function () {
    expect(InvalidCustomerPhone::malformed('MX')->getMessage())->not->toContain('5512345678');
});

it('carries the interface the renderer is registered against', function () {
    expect(InvalidCustomerPhone::malformed('MX'))->toBeInstanceOf(DomainFailure::class);
});

it('has a sentence to show the caller in every locale', function (string $locale) {
    $messages = require dirname(__DIR__, 5)."/lang/{$locale}/messages.php";

    expect($messages['errors'][InvalidCustomerPhone::malformed('MX')->errorCode()] ?? '')
        ->toBeString()->not->toBe('');
})->with(['en', 'es']);
