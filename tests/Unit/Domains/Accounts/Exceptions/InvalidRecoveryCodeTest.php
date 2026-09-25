<?php

declare(strict_types=1);

use App\Domains\Accounts\Application\Dtos\SignInWithGoogleIdTokenInput;
use App\Domains\Accounts\Exceptions\InvalidRecoveryCode;
use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;

it('tells a rejected recovery code apart from one that was merely too long', function () {
    expect(InvalidRecoveryCode::forAccount('01930000-0000-7000-8000-00000000ac01')->getMessage())
        ->toBe('The recovery code given for account [01930000-0000-7000-8000-00000000ac01] is not valid.')
        ->and(InvalidRecoveryCode::tooLong(SignInWithGoogleIdTokenInput::MAXIMUM_RECOVERY_CODE_LENGTH)->getMessage())
        ->toBe('The recovery code exceeds 64 characters.');
});

it('names the limit it was raised with', function () {
    expect(InvalidRecoveryCode::tooLong(21)->getMessage())->toContain('21 characters');
});

it('answers with one error code whatever went wrong with the recovery code', function (InvalidRecoveryCode $failure) {
    expect($failure->errorCode())->toBe('invalid_recovery_code');
})->with([
    'rejected' => fn () => InvalidRecoveryCode::forAccount('account-uuid'),
    'too long' => fn () => InvalidRecoveryCode::tooLong(64),
]);

it('classifies every way as a 422', function (InvalidRecoveryCode $failure) {
    expect($failure->kind())->toBe(DomainFailureKind::Invalid);
})->with([
    'rejected' => fn () => InvalidRecoveryCode::forAccount('account-uuid'),
    'too long' => fn () => InvalidRecoveryCode::tooLong(64),
]);

it('carries the interface the renderer is registered against', function () {
    expect(InvalidRecoveryCode::forAccount('account-uuid'))->toBeInstanceOf(DomainFailure::class);
});

it('has a sentence to show the caller in every locale', function (string $locale) {
    $messages = require dirname(__DIR__, 5)."/lang/{$locale}/messages.php";

    expect($messages['errors']['invalid_recovery_code'] ?? '')->toBeString()->not->toBe('');
})->with(['en', 'es']);

it('is translated into Spanish rather than copied from English', function () {
    $english = require dirname(__DIR__, 5).'/lang/en/messages.php';
    $spanish = require dirname(__DIR__, 5).'/lang/es/messages.php';

    expect($spanish['errors']['invalid_recovery_code'])->not->toBe($english['errors']['invalid_recovery_code']);
});
