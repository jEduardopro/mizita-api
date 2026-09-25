<?php

declare(strict_types=1);

use App\Domains\Accounts\Application\Dtos\ShowAccountReactivationInput;
use App\Domains\Accounts\Exceptions\AccountReactivationNotPending;
use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;
use Tests\Support\Accounts\AccountDeletionFixtures;

it('carries the account the session is waiting to reactivate', function () {
    $input = ShowAccountReactivationInput::forPendingAccount(AccountDeletionFixtures::ACCOUNT_ID);

    expect($input->accountId)->toBe(AccountDeletionFixtures::ACCOUNT_ID)
        ->and(fn () => $input->validate())->not->toThrow(Throwable::class);
});

it('refuses a session with no account waiting as nothing pending', function () {
    expect(fn () => ShowAccountReactivationInput::forPendingAccount(null)->validate())
        ->toThrow(AccountReactivationNotPending::class, 'No account is waiting to be reactivated in this session.');
});

it('refuses a marker that is not a uuid as nothing pending', function (string $pendingAccountId) {
    expect(fn () => ShowAccountReactivationInput::forPendingAccount($pendingAccountId)->validate())
        ->toThrow(AccountReactivationNotPending::class);
})->with([
    'empty' => '',
    'an internal integer key' => '7',
    'garbage' => 'not-a-uuid',
    'a uuid with a trailing newline' => AccountDeletionFixtures::ACCOUNT_ID."\n",
]);

it('classifies nothing pending as not found with a stable code', function () {
    $refusal = AccountReactivationNotPending::inThisSession();

    expect($refusal)->toBeInstanceOf(DomainFailure::class)
        ->and($refusal->errorCode())->toBe('account_reactivation_not_pending')
        ->and($refusal->kind())->toBe(DomainFailureKind::NotFound);
});
