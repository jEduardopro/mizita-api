<?php

declare(strict_types=1);

use App\Domains\Addresses\Exceptions\InvalidAddressPostalCode;
use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;

it('tells an empty postal code apart from a malformed one', function () {
    expect(InvalidAddressPostalCode::empty()->getMessage())->toBe('An address postal code cannot be empty.')
        ->and(InvalidAddressPostalCode::malformed()->getMessage())
        ->toBe('The postal code offered is not a run of digits an address may carry.');
});

it('never quotes the code back, because the message is shown to the caller who typed it', function () {
    expect(InvalidAddressPostalCode::malformed()->getMessage())->not->toContain('[');
});

it('answers with one error code however the postal code fell short', function (InvalidAddressPostalCode $failure) {
    expect($failure->errorCode())->toBe('invalid_address_postal_code');
})->with([
    'empty' => fn () => InvalidAddressPostalCode::empty(),
    'malformed' => fn () => InvalidAddressPostalCode::malformed(),
]);

it('classifies every way as a 422 rather than a conflict or a 500', function (InvalidAddressPostalCode $failure) {
    expect($failure->kind())->toBe(DomainFailureKind::Invalid);
})->with([
    'empty' => fn () => InvalidAddressPostalCode::empty(),
    'malformed' => fn () => InvalidAddressPostalCode::malformed(),
]);

it('carries the interface the renderer is registered against', function () {
    expect(InvalidAddressPostalCode::malformed())->toBeInstanceOf(DomainFailure::class);
});

it('has a sentence to show the caller in every locale', function (string $locale) {
    $messages = require dirname(__DIR__, 5)."/lang/{$locale}/messages.php";

    expect($messages['errors'][InvalidAddressPostalCode::empty()->errorCode()] ?? '')
        ->toBeString()->not->toBe('');
})->with(['en', 'es']);
