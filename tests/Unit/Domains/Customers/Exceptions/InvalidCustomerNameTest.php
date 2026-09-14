<?php

declare(strict_types=1);

use App\Domains\Customers\Exceptions\InvalidCustomerName;
use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;

it('tells an empty name apart from one that is merely too long', function () {
    expect(InvalidCustomerName::empty()->getMessage())->toBe('A customer name cannot be empty.')
        ->and(InvalidCustomerName::tooLong()->getMessage())
        ->toBe('The name offered is longer than a customer name may be.');
});

it('keeps the name itself out of the refusal, because a customer name is personal data', function () {
    expect(InvalidCustomerName::tooLong()->getMessage())->not->toContain('[');
});

it('answers with one error code for both ways a name is refused', function (InvalidCustomerName $failure) {
    expect($failure->errorCode())->toBe('invalid_customer_name');
})->with([
    'empty' => fn () => InvalidCustomerName::empty(),
    'too long' => fn () => InvalidCustomerName::tooLong(),
]);

it('classifies both ways as a 422 rather than a conflict or a 500', function (InvalidCustomerName $failure) {
    expect($failure->kind())->toBe(DomainFailureKind::Invalid);
})->with([
    'empty' => fn () => InvalidCustomerName::empty(),
    'too long' => fn () => InvalidCustomerName::tooLong(),
]);

it('carries the interface the renderer is registered against', function (InvalidCustomerName $failure) {
    expect($failure)->toBeInstanceOf(DomainFailure::class);
})->with([
    'empty' => fn () => InvalidCustomerName::empty(),
    'too long' => fn () => InvalidCustomerName::tooLong(),
]);

it('has a sentence to show the caller in every locale', function (string $locale) {
    $messages = require dirname(__DIR__, 5)."/lang/{$locale}/messages.php";

    expect($messages['errors'][InvalidCustomerName::empty()->errorCode()] ?? '')
        ->toBeString()->not->toBe('');
})->with(['en', 'es']);
