<?php

declare(strict_types=1);

use App\Domains\Customers\Application\Dtos\ListCustomersInput;
use App\Domains\Customers\Exceptions\InvalidCustomerSearch;
use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;

it('says how far the search ran past what is served', function () {
    expect(InvalidCustomerSearch::tooLong(ListCustomersInput::MAXIMUM_SEARCH_LENGTH)->getMessage())
        ->toBe('A customer search may not run past 120 characters.');
});

it('answers with a stable error code', function () {
    expect(InvalidCustomerSearch::tooLong(ListCustomersInput::MAXIMUM_SEARCH_LENGTH)->errorCode())
        ->toBe('invalid_customer_search');
});

it('classifies a search that is too long as a 422 rather than a missing page', function () {
    expect(InvalidCustomerSearch::tooLong(ListCustomersInput::MAXIMUM_SEARCH_LENGTH)->kind())
        ->toBe(DomainFailureKind::Invalid);
});

it('names the limit it was raised with', function () {
    expect(InvalidCustomerSearch::tooLong(40)->getMessage())->toContain('40 characters');
});

it('carries the interface the renderer is registered against', function () {
    expect(InvalidCustomerSearch::tooLong(ListCustomersInput::MAXIMUM_SEARCH_LENGTH))
        ->toBeInstanceOf(DomainFailure::class);
});

it('has a sentence to show the caller in every locale', function (string $locale) {
    $messages = require dirname(__DIR__, 5)."/lang/{$locale}/messages.php";

    expect($messages['errors'][InvalidCustomerSearch::tooLong(1)->errorCode()] ?? '')
        ->toBeString()->not->toBe('');
})->with(['en', 'es']);
