<?php

declare(strict_types=1);

use App\Domains\Addresses\Exceptions\InvalidAddressCity;
use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;

it('tells an empty city apart from one that is merely too long', function () {
    expect(InvalidAddressCity::empty()->getMessage())->toBe('An address city cannot be empty.')
        ->and(InvalidAddressCity::tooLong()->getMessage())
        ->toBe('The city offered is longer than an address city may be.');
});

it('answers with one error code however the city fell short', function (InvalidAddressCity $failure) {
    expect($failure->errorCode())->toBe('invalid_address_city');
})->with([
    'empty' => fn () => InvalidAddressCity::empty(),
    'too long' => fn () => InvalidAddressCity::tooLong(),
]);

it('classifies every way as a 422 rather than a conflict or a 500', function (InvalidAddressCity $failure) {
    expect($failure->kind())->toBe(DomainFailureKind::Invalid);
})->with([
    'empty' => fn () => InvalidAddressCity::empty(),
    'too long' => fn () => InvalidAddressCity::tooLong(),
]);

it('carries the interface the renderer is registered against', function () {
    expect(InvalidAddressCity::empty())->toBeInstanceOf(DomainFailure::class);
});

it('has a sentence to show the caller in every locale', function (string $locale) {
    $messages = require dirname(__DIR__, 5)."/lang/{$locale}/messages.php";

    expect($messages['errors'][InvalidAddressCity::empty()->errorCode()] ?? '')
        ->toBeString()->not->toBe('');
})->with(['en', 'es']);
