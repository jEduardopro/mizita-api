<?php

declare(strict_types=1);

use App\Domains\Addresses\Exceptions\UnsupportedCountry;
use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;

it('quotes the country it turned down', function () {
    expect(UnsupportedCountry::withCode('ES')->getMessage())
        ->toBe('[ES] is not a country this platform operates in.');
});

it('answers with a stable error code', function () {
    expect(UnsupportedCountry::withCode('ES')->errorCode())->toBe('unsupported_country');
});

it('classifies a country we do not operate in as a 422, not a missing page', function () {
    expect(UnsupportedCountry::withCode('ES')->kind())->toBe(DomainFailureKind::Invalid);
});

it('carries the interface the renderer is registered against', function () {
    expect(UnsupportedCountry::withCode('ES'))->toBeInstanceOf(DomainFailure::class);
});

it('has a sentence to show the caller in every locale', function (string $locale) {
    $messages = require dirname(__DIR__, 5)."/lang/{$locale}/messages.php";

    expect($messages['errors'][UnsupportedCountry::withCode('ES')->errorCode()] ?? '')
        ->toBeString()->not->toBe('');
})->with(['en', 'es']);
