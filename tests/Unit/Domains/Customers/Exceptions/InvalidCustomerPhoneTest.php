<?php

declare(strict_types=1);

use App\Domains\Customers\Exceptions\InvalidCustomerPhone;
use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;

it('reports a number longer than the column holds', function () {
    expect(InvalidCustomerPhone::tooLong()->getMessage())
        ->toBe('The number offered is longer than a customer phone may be.');
});

it('keeps the number itself out of the refusal, because a phone number is personal data', function () {
    expect(InvalidCustomerPhone::tooLong()->getMessage())->not->toMatch('/\d/');
});

it('answers with the error code the caller is shown a sentence for', function () {
    expect(InvalidCustomerPhone::tooLong()->errorCode())->toBe('invalid_customer_phone');
});

it('classifies as a 422 rather than a conflict or a 500', function () {
    expect(InvalidCustomerPhone::tooLong()->kind())->toBe(DomainFailureKind::Invalid);
});

it('carries the interface the renderer is registered against', function () {
    expect(InvalidCustomerPhone::tooLong())->toBeInstanceOf(DomainFailure::class);
});

it('has a sentence to show the caller in every locale', function (string $locale) {
    $messages = require dirname(__DIR__, 5)."/lang/{$locale}/messages.php";

    expect($messages['errors'][InvalidCustomerPhone::tooLong()->errorCode()] ?? '')
        ->toBeString()->not->toBe('');
})->with(['en', 'es']);
