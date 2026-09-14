<?php

declare(strict_types=1);

use App\Domains\Businesses\Exceptions\InvalidBusinessName;
use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;

it('tells an empty name apart from one that is merely short', function () {
    expect(InvalidBusinessName::empty()->getMessage())->toBe('A business name cannot be empty.')
        ->and(InvalidBusinessName::tooShort('B')->getMessage())->toBe('[B] is too short for a business name.');
});

it('quotes the short name back, so a log says which one was refused', function () {
    expect(InvalidBusinessName::tooShort('Bo')->getMessage())->toContain('[Bo]');
});

it('quotes the long name back too', function () {
    expect(InvalidBusinessName::tooLong('Barbería Ñandú')->getMessage())
        ->toBe('[Barbería Ñandú] is too long for a business name.');
});

it('answers with one error code however the name fell short', function (InvalidBusinessName $failure) {
    expect($failure->errorCode())->toBe('invalid_business_name');
})->with([
    'empty' => fn () => InvalidBusinessName::empty(),
    'too short' => fn () => InvalidBusinessName::tooShort('B'),
    'too long' => fn () => InvalidBusinessName::tooLong(str_repeat('a', 121)),
]);

it('classifies every way as a 422 rather than a conflict or a 500', function (InvalidBusinessName $failure) {
    expect($failure->kind())->toBe(DomainFailureKind::Invalid);
})->with([
    'empty' => fn () => InvalidBusinessName::empty(),
    'too short' => fn () => InvalidBusinessName::tooShort('B'),
    'too long' => fn () => InvalidBusinessName::tooLong(str_repeat('a', 121)),
]);

it('carries the interface the renderer is registered against', function (InvalidBusinessName $failure) {
    expect($failure)->toBeInstanceOf(DomainFailure::class);
})->with([
    'empty' => fn () => InvalidBusinessName::empty(),
    'too short' => fn () => InvalidBusinessName::tooShort('B'),
    'too long' => fn () => InvalidBusinessName::tooLong(str_repeat('a', 121)),
]);

it('has a sentence to show the caller in every locale', function (string $locale) {
    $messages = require dirname(__DIR__, 5)."/lang/{$locale}/messages.php";

    expect($messages['errors'][InvalidBusinessName::empty()->errorCode()] ?? '')
        ->toBeString()->not->toBe('');
})->with(['en', 'es']);
