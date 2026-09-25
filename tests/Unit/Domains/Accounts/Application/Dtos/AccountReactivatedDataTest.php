<?php

declare(strict_types=1);

use App\Domains\Accounts\Application\Dtos\AccountReactivatedData;
use App\Domains\Accounts\Entities\Account;
use App\Domains\Accounts\ValueObjects\TwoFactorStatus;
use Tests\Support\Accounts\AccountDeletionFixtures;

it('names the reactivated account by its uuid', function () {
    expect(AccountReactivatedData::of(AccountDeletionFixtures::activeAccount())->accountId)
        ->toBe(AccountDeletionFixtures::ACCOUNT_ID);
});

it('says whether the account must still pass its second factor before a session opens', function (TwoFactorStatus $status, bool $requiresSecondFactor) {
    $account = Account::restore(
        AccountDeletionFixtures::ACCOUNT_ID,
        AccountDeletionFixtures::NAME,
        AccountDeletionFixtures::EMAIL,
        new DateTimeImmutable('2025-06-01T08:30:00+00:00'),
        new DateTimeImmutable('2025-05-01T08:30:00+00:00'),
        twoFactorStatus: $status,
    );

    expect(AccountReactivatedData::of($account)->requiresSecondFactor)->toBe($requiresSecondFactor);
})->with([
    'enabled' => [TwoFactorStatus::Enabled, true],
    'set up but never confirmed' => [TwoFactorStatus::Pending, false],
    'disabled' => [TwoFactorStatus::Disabled, false],
]);
