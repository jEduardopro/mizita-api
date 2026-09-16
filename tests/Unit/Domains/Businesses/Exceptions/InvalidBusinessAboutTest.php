<?php

declare(strict_types=1);

use App\Domains\Businesses\Exceptions\InvalidBusinessAbout;
use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;

it('tells an empty description apart from one that is merely long', function () {
    expect(InvalidBusinessAbout::empty()->getMessage())
        ->toBe('A business description cannot be empty.')
        ->and(InvalidBusinessAbout::tooLong(2000)->getMessage())
        ->toBe('A business description cannot be longer than 2000 characters.');
});

it('states the limit it was built with, so the message cannot drift from the value object', function () {
    expect(InvalidBusinessAbout::tooLong(140)->getMessage())->toContain('140 characters');
});

it('answers with one error code however the description fell short', function (InvalidBusinessAbout $failure) {
    expect($failure->errorCode())->toBe('invalid_business_about');
})->with([
    'empty' => fn () => InvalidBusinessAbout::empty(),
    'too long' => fn () => InvalidBusinessAbout::tooLong(2000),
]);

it('classifies every way as a 422 rather than a conflict or a 500', function (InvalidBusinessAbout $failure) {
    expect($failure->kind())->toBe(DomainFailureKind::Invalid);
})->with([
    'empty' => fn () => InvalidBusinessAbout::empty(),
    'too long' => fn () => InvalidBusinessAbout::tooLong(2000),
]);

it('carries the interface the renderer is registered against', function () {
    expect(InvalidBusinessAbout::empty())->toBeInstanceOf(DomainFailure::class);
});

it('has a sentence to show the caller in every locale', function (string $locale) {
    $messages = require dirname(__DIR__, 5)."/lang/{$locale}/messages.php";

    expect($messages['errors'][InvalidBusinessAbout::empty()->errorCode()] ?? '')
        ->toBeString()->not->toBe('');
})->with(['en', 'es']);
