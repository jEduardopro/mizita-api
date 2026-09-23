<?php

declare(strict_types=1);

use App\Domains\Appointments\Exceptions\InvalidGuestAddress;
use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;

it('says what was wrong with the address', function (InvalidGuestAddress $refusal, string $message) {
    expect($refusal->getMessage())->toBe($message);
})->with([
    'no street' => [fn () => InvalidGuestAddress::withoutStreet(), 'A guest address needs a street.'],
    'street too long' => [
        fn () => InvalidGuestAddress::streetTooLong(160),
        'A guest address street is at most 160 characters long.',
    ],
    'city too long' => [
        fn () => InvalidGuestAddress::cityTooLong(120),
        'A guest address city is at most 120 characters long.',
    ],
    'state too long' => [
        fn () => InvalidGuestAddress::stateNameTooLong(120),
        'A guest address state is at most 120 characters long.',
    ],
    'postal code out of bounds' => [
        fn () => InvalidGuestAddress::postalCodeOutOfBounds(4, 10),
        'A guest address postal code runs between 4 and 10 characters.',
    ],
    'malformed country' => [
        fn () => InvalidGuestAddress::malformedCountryCode('MEX'),
        '[MEX] is not a two-letter country code.',
    ],
]);

it('answers with one error code and one kind however the address fell short', function (InvalidGuestAddress $refusal) {
    expect($refusal->errorCode())->toBe('invalid_guest_address')
        ->and($refusal->kind())->toBe(DomainFailureKind::Invalid)
        ->and($refusal)->toBeInstanceOf(DomainFailure::class);
})->with([
    'no street' => [fn () => InvalidGuestAddress::withoutStreet()],
    'street too long' => [fn () => InvalidGuestAddress::streetTooLong(160)],
    'city too long' => [fn () => InvalidGuestAddress::cityTooLong(120)],
    'state too long' => [fn () => InvalidGuestAddress::stateNameTooLong(120)],
    'postal code out of bounds' => [fn () => InvalidGuestAddress::postalCodeOutOfBounds(4, 10)],
    'malformed country' => [fn () => InvalidGuestAddress::malformedCountryCode('MEX')],
    'rejected by a neighbour' => [fn () => InvalidGuestAddress::rejected(new RuntimeException('refused'))],
]);

it('keeps the neighbour refusal it translates as its previous', function () {
    $neighbour = new RuntimeException('A customer address needs a street.');

    $refusal = InvalidGuestAddress::rejected($neighbour);

    expect($refusal->getPrevious())->toBe($neighbour)
        ->and($refusal->getMessage())->toBe('That address was not accepted.');
});

it('has a sentence to show the caller in every locale', function (string $locale) {
    $messages = require dirname(__DIR__, 5)."/lang/{$locale}/messages.php";

    expect($messages['errors'][InvalidGuestAddress::withoutStreet()->errorCode()] ?? '')
        ->toBeString()->not->toBe('');
})->with(['en', 'es']);
