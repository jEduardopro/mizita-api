<?php

declare(strict_types=1);

use App\Domains\Businesses\Exceptions\UnsupportedPhoneNumber;
use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\CountryCode;
use App\Shared\ValueObjects\DomainFailureKind;

it('reports an unserved country without pretending the digits were judged', function () {
    $failure = UnsupportedPhoneNumber::inCountry('ES');

    expect($failure->getMessage())->toBe('[ES] is not a country this platform operates in.');
});

it('reports a number the numbering plan rejects without repeating it', function () {
    $failure = UnsupportedPhoneNumber::forCountry(CountryCode::Mx);

    expect($failure->getMessage())->toBe('The number offered is not a valid phone number in [MX].')
        ->and($failure->getMessage())->not->toContain('5512345678');
});

it('answers with one error code for both ways a number is refused', function (UnsupportedPhoneNumber $failure) {
    expect($failure->errorCode())->toBe('unsupported_phone_number');
})->with([
    'an unserved country' => fn () => UnsupportedPhoneNumber::inCountry('ES'),
    'an unparsable number' => fn () => UnsupportedPhoneNumber::forCountry(CountryCode::Us),
]);

it('classifies both ways as a 422 rather than a conflict or a 500', function (UnsupportedPhoneNumber $failure) {
    expect($failure->kind())->toBe(DomainFailureKind::Invalid);
})->with([
    'an unserved country' => fn () => UnsupportedPhoneNumber::inCountry('ES'),
    'an unparsable number' => fn () => UnsupportedPhoneNumber::forCountry(CountryCode::Us),
]);

it('carries the interface the renderer is registered against', function () {
    expect(UnsupportedPhoneNumber::inCountry('ES'))->toBeInstanceOf(DomainFailure::class);
});

it('has a sentence to show the caller in every locale', function (string $locale) {
    $messages = require dirname(__DIR__, 5)."/lang/{$locale}/messages.php";

    expect($messages['errors'][UnsupportedPhoneNumber::inCountry('ES')->errorCode()] ?? '')
        ->toBeString()->not->toBe('');
})->with(['en', 'es']);

it('reports a number whose shape the payload itself refused, naming only the country', function () {
    expect(UnsupportedPhoneNumber::malformed('MX')->getMessage())
        ->toBe('The number offered for [MX] is not shaped like a phone number.');
});

it('keeps the digits out of a malformed refusal too', function () {
    expect(UnsupportedPhoneNumber::malformed('MX')->getMessage())->not->toMatch('/\d/');
});

it('answers with the same error code when the shape is what was refused', function () {
    expect(UnsupportedPhoneNumber::malformed('MX')->errorCode())->toBe('unsupported_phone_number');
});

it('classifies a malformed number as a 422 as well', function () {
    expect(UnsupportedPhoneNumber::malformed('MX')->kind())->toBe(DomainFailureKind::Invalid);
});

it('carries the renderer interface on a malformed number as well', function () {
    expect(UnsupportedPhoneNumber::malformed('MX'))->toBeInstanceOf(DomainFailure::class);
});
