<?php

declare(strict_types=1);

use App\Domains\Businesses\Exceptions\BusinessNotClosed;
use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;

const NOT_CLOSED_BUSINESS_ID = '01930000-0000-7000-8000-000000000001';

const NOT_CLOSED_ACCOUNT_ID = '01930000-0000-7000-8000-0000000000a9';

it('names the business that is not closed', function () {
    expect(BusinessNotClosed::withId(NOT_CLOSED_BUSINESS_ID)->getMessage())
        ->toBe('Business ['.NOT_CLOSED_BUSINESS_ID.'] is not closed.');
});

it('names the business and the account that did not close it', function () {
    expect(BusinessNotClosed::byAccount(NOT_CLOSED_BUSINESS_ID, NOT_CLOSED_ACCOUNT_ID)->getMessage())
        ->toBe('Business ['.NOT_CLOSED_BUSINESS_ID.'] was not closed by account ['.NOT_CLOSED_ACCOUNT_ID.'].');
});

it('answers with one error code and one kind whichever way it was raised', function (BusinessNotClosed $failure) {
    expect($failure->errorCode())->toBe('business_not_closed')
        ->and($failure->kind())->toBe(DomainFailureKind::Conflict)
        ->and($failure)->toBeInstanceOf(DomainFailure::class);
})->with([
    'not closed at all' => fn () => BusinessNotClosed::withId(NOT_CLOSED_BUSINESS_ID),
    'closed by someone else' => fn () => BusinessNotClosed::byAccount(NOT_CLOSED_BUSINESS_ID, NOT_CLOSED_ACCOUNT_ID),
]);

it('has a sentence to show the caller in every locale', function (string $locale) {
    $messages = require dirname(__DIR__, 5)."/lang/{$locale}/messages.php";

    expect($messages['errors']['business_not_closed'] ?? '')->toBeString()->not->toBe('');
})->with(['en', 'es']);
