<?php

declare(strict_types=1);

use App\Domains\Customers\Entities\Customer;
use App\Domains\Customers\Exceptions\InvalidCustomerBirthDate;
use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;

it('tells apart a date that could not be read from one the calendar refuses to allow', function () {
    expect(InvalidCustomerBirthDate::malformed('17/05/1990')->getMessage())
        ->toBe('The birth date [17/05/1990] is not a calendar date.')
        ->and(InvalidCustomerBirthDate::inTheFuture()->getMessage())
        ->toBe('A birth date cannot be later than today.')
        ->and(InvalidCustomerBirthDate::tooEarly(Customer::EARLIEST_BIRTH_YEAR)->getMessage())
        ->toBe('A birth date cannot be earlier than the year 1900.');
});

it('answers with one error code however the date fell short', function (InvalidCustomerBirthDate $failure) {
    expect($failure->errorCode())->toBe('invalid_customer_birth_date');
})->with([
    'malformed' => fn () => InvalidCustomerBirthDate::malformed('17/05/1990'),
    'in the future' => fn () => InvalidCustomerBirthDate::inTheFuture(),
    'too early' => fn () => InvalidCustomerBirthDate::tooEarly(Customer::EARLIEST_BIRTH_YEAR),
]);

it('classifies every way as a 422 rather than a conflict or a 500', function (InvalidCustomerBirthDate $failure) {
    expect($failure->kind())->toBe(DomainFailureKind::Invalid);
})->with([
    'malformed' => fn () => InvalidCustomerBirthDate::malformed('17/05/1990'),
    'in the future' => fn () => InvalidCustomerBirthDate::inTheFuture(),
    'too early' => fn () => InvalidCustomerBirthDate::tooEarly(Customer::EARLIEST_BIRTH_YEAR),
]);

it('names the earliest year it was raised with', function () {
    expect(InvalidCustomerBirthDate::tooEarly(1800)->getMessage())->toContain('the year 1800');
});

it('carries the interface the renderer is registered against', function () {
    expect(InvalidCustomerBirthDate::inTheFuture())->toBeInstanceOf(DomainFailure::class);
});

it('has a sentence to show the caller in every locale', function (string $locale) {
    $messages = require dirname(__DIR__, 5)."/lang/{$locale}/messages.php";

    expect($messages['errors'][InvalidCustomerBirthDate::inTheFuture()->errorCode()] ?? '')
        ->toBeString()->not->toBe('');
})->with(['en', 'es']);
