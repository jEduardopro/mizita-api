<?php

declare(strict_types=1);

use App\Domains\Addresses\Exceptions\InvalidAddressCity;
use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;

it('says the city offered is longer than one may be', function () {
    expect(InvalidAddressCity::tooLong()->getMessage())
        ->toBe('The city offered is longer than an address city may be.');
});

it('never quotes the city back, because the message is shown to the caller who typed it', function () {
    expect(InvalidAddressCity::tooLong()->getMessage())->not->toContain('[');
});

it('answers with one error code however the city fell short', function () {
    expect(InvalidAddressCity::tooLong()->errorCode())->toBe('invalid_address_city');
});

it('classifies a city that is too long as a 422 rather than a conflict or a 500', function () {
    expect(InvalidAddressCity::tooLong()->kind())->toBe(DomainFailureKind::Invalid);
});

it('carries the interface the renderer is registered against', function () {
    expect(InvalidAddressCity::tooLong())->toBeInstanceOf(DomainFailure::class);
});

it('has a sentence to show the caller in every locale', function (string $locale) {
    $messages = require dirname(__DIR__, 5)."/lang/{$locale}/messages.php";

    expect($messages['errors'][InvalidAddressCity::tooLong()->errorCode()] ?? '')
        ->toBeString()->not->toBe('');
})->with(['en', 'es']);
