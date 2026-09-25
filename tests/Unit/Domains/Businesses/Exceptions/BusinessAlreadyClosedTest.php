<?php

declare(strict_types=1);

use App\Domains\Businesses\Exceptions\BusinessAlreadyClosed;
use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;

const ALREADY_CLOSED_BUSINESS_ID = '01930000-0000-7000-8000-000000000001';

it('names the business that is already closed', function () {
    expect(BusinessAlreadyClosed::withId(ALREADY_CLOSED_BUSINESS_ID)->getMessage())
        ->toBe('Business ['.ALREADY_CLOSED_BUSINESS_ID.'] is already closed.');
});

it('answers with the error code the caller is shown a sentence for', function () {
    expect(BusinessAlreadyClosed::withId(ALREADY_CLOSED_BUSINESS_ID)->errorCode())->toBe('business_already_closed');
});

it('classifies as a conflict with the state the business is in', function () {
    expect(BusinessAlreadyClosed::withId(ALREADY_CLOSED_BUSINESS_ID)->kind())->toBe(DomainFailureKind::Conflict);
});

it('carries the interface the renderer is registered against', function () {
    expect(BusinessAlreadyClosed::withId(ALREADY_CLOSED_BUSINESS_ID))->toBeInstanceOf(DomainFailure::class);
});

it('has a sentence to show the caller in every locale', function (string $locale) {
    $messages = require dirname(__DIR__, 5)."/lang/{$locale}/messages.php";

    expect($messages['errors']['business_already_closed'] ?? '')->toBeString()->not->toBe('');
})->with(['en', 'es']);
