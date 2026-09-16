<?php

declare(strict_types=1);

use App\Domains\Addresses\Exceptions\InvalidAddressStreet;
use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;

it('tells an empty street apart from one that is merely too long', function () {
    expect(InvalidAddressStreet::empty()->getMessage())->toBe('An address street cannot be empty.')
        ->and(InvalidAddressStreet::tooLong()->getMessage())
        ->toBe('The street offered is longer than an address street may be.');
});

it('answers with one error code however the street fell short', function (InvalidAddressStreet $failure) {
    expect($failure->errorCode())->toBe('invalid_address_street');
})->with([
    'empty' => fn () => InvalidAddressStreet::empty(),
    'too long' => fn () => InvalidAddressStreet::tooLong(),
]);

it('classifies every way as a 422 rather than a conflict or a 500', function (InvalidAddressStreet $failure) {
    expect($failure->kind())->toBe(DomainFailureKind::Invalid);
})->with([
    'empty' => fn () => InvalidAddressStreet::empty(),
    'too long' => fn () => InvalidAddressStreet::tooLong(),
]);

it('carries the interface the renderer is registered against', function () {
    expect(InvalidAddressStreet::empty())->toBeInstanceOf(DomainFailure::class);
});

it('has a sentence to show the caller in every locale', function (string $locale) {
    $messages = require dirname(__DIR__, 5)."/lang/{$locale}/messages.php";

    expect($messages['errors'][InvalidAddressStreet::empty()->errorCode()] ?? '')
        ->toBeString()->not->toBe('');
})->with(['en', 'es']);
