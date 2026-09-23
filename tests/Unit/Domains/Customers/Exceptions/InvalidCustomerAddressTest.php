<?php

declare(strict_types=1);

use App\Domains\Customers\Exceptions\InvalidCustomerAddress;
use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;

it('says what was wrong with the address', function (InvalidCustomerAddress $refusal, string $message) {
    expect($refusal->getMessage())->toBe($message);
})->with([
    'no street' => [fn () => InvalidCustomerAddress::withoutStreet(), 'A customer address needs a street.'],
    'street too long' => [
        fn () => InvalidCustomerAddress::streetTooLong(160),
        'A customer address street may not run past 160 characters.',
    ],
    'city too long' => [
        fn () => InvalidCustomerAddress::cityTooLong(120),
        'A customer address city may not run past 120 characters.',
    ],
    'state too long' => [
        fn () => InvalidCustomerAddress::stateNameTooLong(120),
        'A customer address state may not run past 120 characters.',
    ],
    'postal code out of bounds' => [
        fn () => InvalidCustomerAddress::postalCodeOutOfBounds(4, 10),
        'A customer address postal code runs between 4 and 10 characters.',
    ],
    'malformed country' => [
        fn () => InvalidCustomerAddress::malformedCountryCode('MEX'),
        '[MEX] is not a two-letter country code.',
    ],
]);

it('answers with one error code and one kind however the address fell short', function (InvalidCustomerAddress $refusal) {
    expect($refusal->errorCode())->toBe('invalid_customer_address')
        ->and($refusal->kind())->toBe(DomainFailureKind::Invalid)
        ->and($refusal)->toBeInstanceOf(DomainFailure::class);
})->with([
    'no street' => [fn () => InvalidCustomerAddress::withoutStreet()],
    'street too long' => [fn () => InvalidCustomerAddress::streetTooLong(160)],
    'city too long' => [fn () => InvalidCustomerAddress::cityTooLong(120)],
    'state too long' => [fn () => InvalidCustomerAddress::stateNameTooLong(120)],
    'postal code out of bounds' => [fn () => InvalidCustomerAddress::postalCodeOutOfBounds(4, 10)],
    'malformed country' => [fn () => InvalidCustomerAddress::malformedCountryCode('MEX')],
]);

it('has a sentence to show the caller in every locale', function (string $locale) {
    $messages = require dirname(__DIR__, 5)."/lang/{$locale}/messages.php";

    expect($messages['errors'][InvalidCustomerAddress::withoutStreet()->errorCode()] ?? '')
        ->toBeString()->not->toBe('');
})->with(['en', 'es']);
