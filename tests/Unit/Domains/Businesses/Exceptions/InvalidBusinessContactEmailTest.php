<?php

declare(strict_types=1);

use App\Domains\Businesses\Exceptions\InvalidBusinessContactEmail;
use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;

it('tells the three ways an address is refused apart', function () {
    expect(InvalidBusinessContactEmail::empty()->getMessage())
        ->toBe('A business contact email cannot be empty.')
        ->and(InvalidBusinessContactEmail::tooLong(255)->getMessage())
        ->toBe('A business contact email cannot be longer than 255 characters.')
        ->and(InvalidBusinessContactEmail::malformed()->getMessage())
        ->toBe('That is not a well formed business contact email.');
});

it('states the limit it was built with, so the message cannot drift from the value object', function () {
    expect(InvalidBusinessContactEmail::tooLong(120)->getMessage())->toContain('120 characters');
});

it('never quotes the address back, because a log is not the place for it', function () {
    expect(InvalidBusinessContactEmail::malformed()->getMessage())->not->toContain('@');
});

it('answers with one error code however the address fell short', function (InvalidBusinessContactEmail $failure) {
    expect($failure->errorCode())->toBe('invalid_business_contact_email');
})->with([
    'empty' => fn () => InvalidBusinessContactEmail::empty(),
    'too long' => fn () => InvalidBusinessContactEmail::tooLong(255),
    'malformed' => fn () => InvalidBusinessContactEmail::malformed(),
]);

it('classifies every way as a 422 rather than a conflict or a 500', function (InvalidBusinessContactEmail $failure) {
    expect($failure->kind())->toBe(DomainFailureKind::Invalid);
})->with([
    'empty' => fn () => InvalidBusinessContactEmail::empty(),
    'too long' => fn () => InvalidBusinessContactEmail::tooLong(255),
    'malformed' => fn () => InvalidBusinessContactEmail::malformed(),
]);

it('carries the interface the renderer is registered against', function () {
    expect(InvalidBusinessContactEmail::malformed())->toBeInstanceOf(DomainFailure::class);
});

it('has a sentence to show the caller in every locale', function (string $locale) {
    $messages = require dirname(__DIR__, 5)."/lang/{$locale}/messages.php";

    expect($messages['errors'][InvalidBusinessContactEmail::malformed()->errorCode()] ?? '')
        ->toBeString()->not->toBe('');
})->with(['en', 'es']);
