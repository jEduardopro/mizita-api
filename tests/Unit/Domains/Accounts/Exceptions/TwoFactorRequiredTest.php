<?php

declare(strict_types=1);

use App\Domains\Accounts\Exceptions\TwoFactorRequired;
use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;

it('names the account that still has to pass its second factor', function () {
    expect(TwoFactorRequired::forAccount('01930000-0000-7000-8000-00000000ac01')->getMessage())
        ->toBe('Account [01930000-0000-7000-8000-00000000ac01] requires a second factor to sign in.');
});

it('answers with the two_factor_required code', function () {
    expect(TwoFactorRequired::forAccount('account-uuid')->errorCode())->toBe('two_factor_required');
});

it('is unauthenticated, so the api answers 401 rather than 403 or 422', function () {
    expect(TwoFactorRequired::forAccount('account-uuid')->kind())->toBe(DomainFailureKind::Unauthenticated);
});

it('carries the interface the renderer is registered against', function () {
    expect(TwoFactorRequired::forAccount('account-uuid'))->toBeInstanceOf(DomainFailure::class);
});

it('has a sentence to show the caller in every locale', function (string $locale) {
    $messages = require dirname(__DIR__, 5)."/lang/{$locale}/messages.php";

    expect($messages['errors']['two_factor_required'] ?? '')->toBeString()->not->toBe('');
})->with(['en', 'es']);

it('is translated into Spanish rather than copied from English', function () {
    $english = require dirname(__DIR__, 5).'/lang/en/messages.php';
    $spanish = require dirname(__DIR__, 5).'/lang/es/messages.php';

    expect($spanish['errors']['two_factor_required'])->not->toBe($english['errors']['two_factor_required']);
});
