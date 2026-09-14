<?php

declare(strict_types=1);

use App\Domains\Businesses\Exceptions\UnsupportedPhoneNumber;
use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\CountryCode;
use App\Shared\ValueObjects\DomainFailureKind;

/*
| The verdict on a phone number moved out of the FormRequest and into the use
| case, so this exception is now the only thing standing between a number the
| platform cannot dial and a 500. What makes it a translated 422 instead is the
| DomainFailure pair below and a key in both locale files - none of which any
| other test would notice the absence of.
*/

it('reports an unserved country without pretending the digits were judged', function () {
    // Nothing about the number has been parsed at this point: the country was
    // refused first, so a message about the number would not even be true.
    $failure = UnsupportedPhoneNumber::inCountry('ES');

    expect($failure->getMessage())->toBe('[ES] is not a country this platform operates in.');
});

it('reports a number the numbering plan rejects without repeating it', function () {
    // These strings end up in logs, and a phone number is personal data.
    $failure = UnsupportedPhoneNumber::forCountry(CountryCode::Mx);

    expect($failure->getMessage())->toBe('The number offered is not a valid phone number in [MX].')
        ->and($failure->getMessage())->not->toContain('5512345678');
});

it('answers with one error code for both ways a number is refused', function (UnsupportedPhoneNumber $failure) {
    // To the person at the form they are the same fact, so two codes would be a
    // distinction they have no use for.
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
    // Registered once against DomainFailure, so an exception that forgot it is a
    // 500 with an English developer string on the wire and nothing fails first.
    expect(UnsupportedPhoneNumber::inCountry('ES'))->toBeInstanceOf(DomainFailure::class);
});

it('has a sentence to show the caller in every locale', function (string $locale) {
    // The wire message is always the translation, never getMessage(). A missing
    // key here means the caller is shown the raw error code.
    $messages = require dirname(__DIR__, 5)."/lang/{$locale}/messages.php";

    expect($messages['errors'][UnsupportedPhoneNumber::inCountry('ES')->errorCode()] ?? '')
        ->toBeString()->not->toBe('');
})->with(['en', 'es']);
