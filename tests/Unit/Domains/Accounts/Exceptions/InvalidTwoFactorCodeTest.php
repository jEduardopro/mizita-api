<?php

declare(strict_types=1);

use App\Domains\Accounts\Application\Dtos\SignInWithGoogleIdTokenInput;
use App\Domains\Accounts\Exceptions\InvalidTwoFactorCode;
use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;

it('tells a rejected code apart from a payload that was malformed', function () {
    expect(InvalidTwoFactorCode::forAccount('01930000-0000-7000-8000-00000000ac01')->getMessage())
        ->toBe('The two factor code given for account [01930000-0000-7000-8000-00000000ac01] is not valid.')
        ->and(InvalidTwoFactorCode::conflictingProofs()->getMessage())
        ->toBe('A two factor code and a recovery code cannot be given together.')
        ->and(InvalidTwoFactorCode::tooLong(SignInWithGoogleIdTokenInput::MAXIMUM_CODE_LENGTH)->getMessage())
        ->toBe('The two factor code exceeds 16 characters.');
});

it('names the limit it was raised with', function () {
    expect(InvalidTwoFactorCode::tooLong(8)->getMessage())->toContain('8 characters');
});

it('answers with one error code whatever went wrong with the code', function (InvalidTwoFactorCode $failure) {
    expect($failure->errorCode())->toBe('invalid_two_factor_code');
})->with([
    'rejected' => fn () => InvalidTwoFactorCode::forAccount('account-uuid'),
    'given alongside a recovery code' => fn () => InvalidTwoFactorCode::conflictingProofs(),
    'too long' => fn () => InvalidTwoFactorCode::tooLong(16),
]);

it('classifies every way as a 422', function (InvalidTwoFactorCode $failure) {
    expect($failure->kind())->toBe(DomainFailureKind::Invalid);
})->with([
    'rejected' => fn () => InvalidTwoFactorCode::forAccount('account-uuid'),
    'given alongside a recovery code' => fn () => InvalidTwoFactorCode::conflictingProofs(),
    'too long' => fn () => InvalidTwoFactorCode::tooLong(16),
]);

it('carries the interface the renderer is registered against', function () {
    expect(InvalidTwoFactorCode::forAccount('account-uuid'))->toBeInstanceOf(DomainFailure::class);
});

it('has a sentence to show the caller in every locale', function (string $locale) {
    $messages = require dirname(__DIR__, 5)."/lang/{$locale}/messages.php";

    expect($messages['errors']['invalid_two_factor_code'] ?? '')->toBeString()->not->toBe('');
})->with(['en', 'es']);

it('is translated into Spanish rather than copied from English', function () {
    $english = require dirname(__DIR__, 5).'/lang/en/messages.php';
    $spanish = require dirname(__DIR__, 5).'/lang/es/messages.php';

    expect($spanish['errors']['invalid_two_factor_code'])->not->toBe($english['errors']['invalid_two_factor_code']);
});
