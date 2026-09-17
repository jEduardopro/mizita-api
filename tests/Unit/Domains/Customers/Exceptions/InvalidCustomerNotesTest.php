<?php

declare(strict_types=1);

use App\Domains\Customers\Entities\Customer;
use App\Domains\Customers\Exceptions\InvalidCustomerNotes;
use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;

it('says how far the notes ran past what is kept', function () {
    expect(InvalidCustomerNotes::tooLong(Customer::MAXIMUM_NOTES_LENGTH)->getMessage())
        ->toBe('Customer notes may not run past 2000 characters.');
});

it('answers with a stable error code', function () {
    expect(InvalidCustomerNotes::tooLong(Customer::MAXIMUM_NOTES_LENGTH)->errorCode())
        ->toBe('invalid_customer_notes');
});

it('classifies notes that are too long as a 422 rather than a conflict or a 500', function () {
    expect(InvalidCustomerNotes::tooLong(Customer::MAXIMUM_NOTES_LENGTH)->kind())
        ->toBe(DomainFailureKind::Invalid);
});

it('names the limit it was raised with', function () {
    expect(InvalidCustomerNotes::tooLong(500)->getMessage())->toContain('500 characters');
});

it('carries the interface the renderer is registered against', function () {
    expect(InvalidCustomerNotes::tooLong(Customer::MAXIMUM_NOTES_LENGTH))
        ->toBeInstanceOf(DomainFailure::class);
});

it('has a sentence to show the caller in every locale', function (string $locale) {
    $messages = require dirname(__DIR__, 5)."/lang/{$locale}/messages.php";

    expect($messages['errors'][InvalidCustomerNotes::tooLong(1)->errorCode()] ?? '')
        ->toBeString()->not->toBe('');
})->with(['en', 'es']);
