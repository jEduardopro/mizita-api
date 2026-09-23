<?php

declare(strict_types=1);

use App\Domains\PublicCatalog\Exceptions\InvalidPublicGuestAddress;
use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;

dataset('invalid public guest addresses', [
    'street too long' => [fn () => InvalidPublicGuestAddress::streetTooLong(160)],
    'city too long' => [fn () => InvalidPublicGuestAddress::cityTooLong(120)],
    'state too long' => [fn () => InvalidPublicGuestAddress::stateTooLong(120)],
    'postal code out of bounds' => [fn () => InvalidPublicGuestAddress::postalCodeOutOfBounds(4, 10)],
]);

it('answers with one error code and one kind however the address fell short', function (InvalidPublicGuestAddress $refusal) {
    expect($refusal)->toBeInstanceOf(DomainFailure::class)
        ->and($refusal->errorCode())->toBe('invalid_guest_address')
        ->and($refusal->kind())->toBe(DomainFailureKind::Invalid);
})->with('invalid public guest addresses');

it('has a sentence to show the visitor in every locale', function (string $locale) {
    $messages = require dirname(__DIR__, 5)."/lang/{$locale}/messages.php";

    expect($messages['errors'][InvalidPublicGuestAddress::streetTooLong(160)->errorCode()] ?? '')
        ->toBeString()->not->toBe('');
})->with(['en', 'es']);
