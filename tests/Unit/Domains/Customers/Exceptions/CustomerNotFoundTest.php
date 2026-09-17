<?php

declare(strict_types=1);

use App\Domains\Customers\Exceptions\CustomerNotFound;
use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;

it('quotes the customer it could not find', function () {
    expect(CustomerNotFound::withId('01930000-0000-7000-8000-0000000000c1')->getMessage())
        ->toBe('Customer [01930000-0000-7000-8000-0000000000c1] was not found.');
});

it('answers with a stable error code', function () {
    expect(CustomerNotFound::withId('01930000-0000-7000-8000-0000000000c1')->errorCode())
        ->toBe('customer_not_found');
});

it('classifies a customer nobody can reach as a missing page, not a refusal of the payload', function () {
    expect(CustomerNotFound::withId('01930000-0000-7000-8000-0000000000c1')->kind())
        ->toBe(DomainFailureKind::NotFound);
});

it('answers the same way to a customer of another business as to one that never existed', function () {
    expect(CustomerNotFound::withId('01930000-0000-7000-8000-0000000000c2')->kind())
        ->toBe(DomainFailureKind::NotFound)
        ->and(CustomerNotFound::withId('not-a-uuid')->kind())->toBe(DomainFailureKind::NotFound);
});

it('carries the interface the renderer is registered against', function () {
    expect(CustomerNotFound::withId('01930000-0000-7000-8000-0000000000c1'))
        ->toBeInstanceOf(DomainFailure::class);
});

it('has a sentence to show the caller in every locale', function (string $locale) {
    $messages = require dirname(__DIR__, 5)."/lang/{$locale}/messages.php";

    expect($messages['errors'][CustomerNotFound::withId('any')->errorCode()] ?? '')
        ->toBeString()->not->toBe('');
})->with(['en', 'es']);
