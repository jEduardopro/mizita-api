<?php

declare(strict_types=1);

use App\Domains\Addresses\Exceptions\AddressCityCannotBeCleared;
use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;

it('says a city that already has a value cannot be emptied', function () {
    expect(AddressCityCannotBeCleared::alreadySet()->getMessage())
        ->toBe('An address city that already has a value cannot be emptied.');
});

it('answers with the error code the front end paints the city box from', function () {
    expect(AddressCityCannotBeCleared::alreadySet()->errorCode())->toBe('address_city_cannot_be_cleared');
});

it('classifies the refusal as a conflict, because the payload is well formed', function () {
    expect(AddressCityCannotBeCleared::alreadySet()->kind())->toBe(DomainFailureKind::Conflict);
});

it('carries the interface the renderer is registered against', function () {
    expect(AddressCityCannotBeCleared::alreadySet())->toBeInstanceOf(DomainFailure::class);
});

it('names a different code from the postal code refusal, so the right box lights up', function () {
    expect(AddressCityCannotBeCleared::alreadySet()->errorCode())
        ->not->toBe('address_postal_code_cannot_be_cleared');
});

it('has a sentence to show the caller in every locale', function (string $locale) {
    $messages = require dirname(__DIR__, 5)."/lang/{$locale}/messages.php";

    expect($messages['errors'][AddressCityCannotBeCleared::alreadySet()->errorCode()] ?? '')
        ->toBeString()->not->toBe('');
})->with(['en', 'es']);
