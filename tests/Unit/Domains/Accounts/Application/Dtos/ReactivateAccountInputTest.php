<?php

declare(strict_types=1);

use App\Domains\Accounts\Application\Dtos\ReactivateAccountInput;
use App\Domains\Accounts\Exceptions\AccountReactivationNotPending;
use Tests\Support\Accounts\AccountDeletionFixtures;

it('carries the account the session is waiting to reactivate', function () {
    $input = ReactivateAccountInput::forPendingAccount(AccountDeletionFixtures::ACCOUNT_ID);

    expect($input->accountId)->toBe(AccountDeletionFixtures::ACCOUNT_ID)
        ->and(fn () => $input->validate())->not->toThrow(Throwable::class);
});

it('refuses a session with no account waiting as nothing pending', function () {
    expect(fn () => ReactivateAccountInput::forPendingAccount(null)->validate())
        ->toThrow(AccountReactivationNotPending::class, 'No account is waiting to be reactivated in this session.');
});

it('refuses a marker that is not a uuid as nothing pending', function (string $pendingAccountId) {
    expect(fn () => ReactivateAccountInput::forPendingAccount($pendingAccountId)->validate())
        ->toThrow(AccountReactivationNotPending::class);
})->with([
    'empty' => '',
    'an internal integer key' => '7',
    'garbage' => 'not-a-uuid',
    'a uuid with a trailing newline' => AccountDeletionFixtures::ACCOUNT_ID."\n",
]);
