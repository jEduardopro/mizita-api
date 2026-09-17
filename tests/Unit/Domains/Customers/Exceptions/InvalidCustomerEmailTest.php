<?php

declare(strict_types=1);

use App\Domains\Customers\Exceptions\InvalidCustomerEmail;
use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;

it('tells apart the three ways an email can fail to be one', function () {
    expect(InvalidCustomerEmail::empty()->getMessage())->toBe('A customer email cannot be empty.')
        ->and(InvalidCustomerEmail::malformed()->getMessage())
        ->toBe('The email offered is not a valid email address.')
        ->and(InvalidCustomerEmail::tooLong()->getMessage())
        ->toBe('The email offered is longer than a customer email may be.');
});

it('answers with one error code however the email fell short', function (InvalidCustomerEmail $failure) {
    expect($failure->errorCode())->toBe('invalid_customer_email');
})->with([
    'empty' => fn () => InvalidCustomerEmail::empty(),
    'malformed' => fn () => InvalidCustomerEmail::malformed(),
    'too long' => fn () => InvalidCustomerEmail::tooLong(),
]);

it('classifies every way as a 422 rather than a conflict or a 500', function (InvalidCustomerEmail $failure) {
    expect($failure->kind())->toBe(DomainFailureKind::Invalid);
})->with([
    'empty' => fn () => InvalidCustomerEmail::empty(),
    'malformed' => fn () => InvalidCustomerEmail::malformed(),
    'too long' => fn () => InvalidCustomerEmail::tooLong(),
]);

it('never quotes the address it refused, which a log would otherwise carry', function (InvalidCustomerEmail $failure) {
    expect($failure->getMessage())->not->toContain('@');
})->with([
    'empty' => fn () => InvalidCustomerEmail::empty(),
    'malformed' => fn () => InvalidCustomerEmail::malformed(),
    'too long' => fn () => InvalidCustomerEmail::tooLong(),
]);

it('carries the interface the renderer is registered against', function () {
    expect(InvalidCustomerEmail::malformed())->toBeInstanceOf(DomainFailure::class);
});

it('has a sentence to show the caller in every locale', function (string $locale) {
    $messages = require dirname(__DIR__, 5)."/lang/{$locale}/messages.php";

    expect($messages['errors'][InvalidCustomerEmail::malformed()->errorCode()] ?? '')
        ->toBeString()->not->toBe('');
})->with(['en', 'es']);
