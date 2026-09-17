<?php

declare(strict_types=1);

use App\Domains\Customers\Entities\Customer;
use App\Domains\Customers\Exceptions\InvalidCustomerName;
use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;

it('tells an empty name apart from one that is merely too long', function () {
    expect(InvalidCustomerName::empty()->getMessage())->toBe('A customer name cannot be empty.')
        ->and(InvalidCustomerName::tooLong(Customer::MAXIMUM_NAME_LENGTH)->getMessage())
        ->toBe('A customer name may not run past 120 characters.');
});

it('answers with one error code however the name fell short', function (InvalidCustomerName $failure) {
    expect($failure->errorCode())->toBe('invalid_customer_name');
})->with([
    'empty' => fn () => InvalidCustomerName::empty(),
    'too long' => fn () => InvalidCustomerName::tooLong(Customer::MAXIMUM_NAME_LENGTH),
]);

it('classifies every way as a 422 rather than a conflict or a 500', function (InvalidCustomerName $failure) {
    expect($failure->kind())->toBe(DomainFailureKind::Invalid);
})->with([
    'empty' => fn () => InvalidCustomerName::empty(),
    'too long' => fn () => InvalidCustomerName::tooLong(Customer::MAXIMUM_NAME_LENGTH),
]);

it('names the limit it was raised with, so the developer string says which one was crossed', function () {
    expect(InvalidCustomerName::tooLong(40)->getMessage())->toContain('40 characters');
});

it('carries the interface the renderer is registered against', function () {
    expect(InvalidCustomerName::empty())->toBeInstanceOf(DomainFailure::class);
});

it('has a sentence to show the caller in every locale', function (string $locale) {
    $messages = require dirname(__DIR__, 5)."/lang/{$locale}/messages.php";

    expect($messages['errors'][InvalidCustomerName::empty()->errorCode()] ?? '')
        ->toBeString()->not->toBe('');
})->with(['en', 'es']);
