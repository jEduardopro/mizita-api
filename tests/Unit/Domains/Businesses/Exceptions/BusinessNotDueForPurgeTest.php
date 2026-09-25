<?php

declare(strict_types=1);

use App\Domains\Businesses\Exceptions\BusinessNotDueForPurge;
use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;

const NOT_DUE_BUSINESS_ID = '01930000-0000-7000-8000-000000000001';

it('names the business and the instant it becomes due', function () {
    expect(BusinessNotDueForPurge::until(NOT_DUE_BUSINESS_ID, new DateTimeImmutable('2026-03-03T10:00:00+00:00'))->getMessage())
        ->toBe('Business ['.NOT_DUE_BUSINESS_ID.'] cannot be purged before [2026-03-03T10:00:00+00:00].');
});

it('answers with the error code the caller is shown a sentence for, as a conflict', function () {
    $failure = BusinessNotDueForPurge::until(NOT_DUE_BUSINESS_ID, new DateTimeImmutable('2026-03-03T10:00:00+00:00'));

    expect($failure->errorCode())->toBe('business_not_due_for_purge')
        ->and($failure->kind())->toBe(DomainFailureKind::Conflict)
        ->and($failure)->toBeInstanceOf(DomainFailure::class);
});

it('has a sentence to show the caller in every locale', function (string $locale) {
    $messages = require dirname(__DIR__, 5)."/lang/{$locale}/messages.php";

    expect($messages['errors']['business_not_due_for_purge'] ?? '')->toBeString()->not->toBe('');
})->with(['en', 'es']);
