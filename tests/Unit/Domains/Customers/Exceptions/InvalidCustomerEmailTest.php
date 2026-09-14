<?php

declare(strict_types=1);

use App\Domains\Customers\Exceptions\InvalidCustomerEmail;
use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;

it('tells a malformed address apart from one that is merely too long', function () {
    expect(InvalidCustomerEmail::malformed()->getMessage())
        ->toBe('The address offered is not a valid email address.')
        ->and(InvalidCustomerEmail::tooLong()->getMessage())
        ->toBe('The address offered is longer than a customer email may be.');
});

it('keeps the address itself out of the refusal, because these strings reach logs', function (InvalidCustomerEmail $failure) {
    expect($failure->getMessage())->not->toContain('@');
})->with([
    'malformed' => fn () => InvalidCustomerEmail::malformed(),
    'too long' => fn () => InvalidCustomerEmail::tooLong(),
]);

it('answers with one error code for both ways an address is refused', function (InvalidCustomerEmail $failure) {
    expect($failure->errorCode())->toBe('invalid_customer_email');
})->with([
    'malformed' => fn () => InvalidCustomerEmail::malformed(),
    'too long' => fn () => InvalidCustomerEmail::tooLong(),
]);

it('classifies both ways as a 422 rather than a conflict or a 500', function (InvalidCustomerEmail $failure) {
    expect($failure->kind())->toBe(DomainFailureKind::Invalid);
})->with([
    'malformed' => fn () => InvalidCustomerEmail::malformed(),
    'too long' => fn () => InvalidCustomerEmail::tooLong(),
]);

it('carries the interface the renderer is registered against', function (InvalidCustomerEmail $failure) {
    expect($failure)->toBeInstanceOf(DomainFailure::class);
})->with([
    'malformed' => fn () => InvalidCustomerEmail::malformed(),
    'too long' => fn () => InvalidCustomerEmail::tooLong(),
]);

it('has a sentence to show the caller in every locale', function (string $locale) {
    $messages = require dirname(__DIR__, 5)."/lang/{$locale}/messages.php";

    expect($messages['errors'][InvalidCustomerEmail::malformed()->errorCode()] ?? '')
        ->toBeString()->not->toBe('');
})->with(['en', 'es']);
