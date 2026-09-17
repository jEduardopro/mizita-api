<?php

declare(strict_types=1);

use App\Domains\Addresses\Exceptions\AddressPostalCodeCannotBeCleared;
use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;

it('says a postal code that already has a value cannot be emptied', function () {
    expect(AddressPostalCodeCannotBeCleared::alreadySet()->getMessage())
        ->toBe('An address postal code that already has a value cannot be emptied.');
});

it('answers with the error code the front end paints the postal code box from', function () {
    expect(AddressPostalCodeCannotBeCleared::alreadySet()->errorCode())
        ->toBe('address_postal_code_cannot_be_cleared');
});

it('classifies the refusal as a conflict, because the payload is well formed', function () {
    expect(AddressPostalCodeCannotBeCleared::alreadySet()->kind())->toBe(DomainFailureKind::Conflict);
});

it('carries the interface the renderer is registered against', function () {
    expect(AddressPostalCodeCannotBeCleared::alreadySet())->toBeInstanceOf(DomainFailure::class);
});

it('names a different code from the city refusal, so the right box lights up', function () {
    expect(AddressPostalCodeCannotBeCleared::alreadySet()->errorCode())
        ->not->toBe('address_city_cannot_be_cleared');
});

it('has a sentence to show the caller in every locale', function (string $locale) {
    $messages = require dirname(__DIR__, 5)."/lang/{$locale}/messages.php";

    expect($messages['errors'][AddressPostalCodeCannotBeCleared::alreadySet()->errorCode()] ?? '')
        ->toBeString()->not->toBe('');
})->with(['en', 'es']);
