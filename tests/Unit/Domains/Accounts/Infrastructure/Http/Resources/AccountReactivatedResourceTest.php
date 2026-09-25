<?php

declare(strict_types=1);

use App\Domains\Accounts\Application\Dtos\AccountReactivatedData;
use App\Domains\Accounts\Entities\Account;
use App\Domains\Accounts\Infrastructure\Http\Resources\AccountReactivatedResource;
use App\Domains\Accounts\ValueObjects\TwoFactorStatus;
use Tests\Support\Accounts\AccountDeletionFixtures;
use Tests\TestCase;

uses(TestCase::class);

/**
 * @return array<string, mixed>
 */
function serializedReactivated(TwoFactorStatus $status): array
{
    return AccountReactivatedResource::make(AccountReactivatedData::of(Account::restore(
        AccountDeletionFixtures::ACCOUNT_ID,
        AccountDeletionFixtures::NAME,
        AccountDeletionFixtures::EMAIL,
        new DateTimeImmutable('2025-06-01T08:30:00+00:00'),
        new DateTimeImmutable('2025-05-01T08:30:00+00:00'),
        twoFactorStatus: $status,
    )))->response()->getData(true);
}

it('tells the client a second factor is still owed, wrapped in data', function () {
    expect(serializedReactivated(TwoFactorStatus::Enabled))->toBe(['data' => ['two_factor_required' => true]]);
});

it('tells the client no second factor is owed when the account has none confirmed', function (TwoFactorStatus $status) {
    expect(serializedReactivated($status))->toBe(['data' => ['two_factor_required' => false]]);
})->with([
    'disabled' => TwoFactorStatus::Disabled,
    'set up but never confirmed' => TwoFactorStatus::Pending,
]);

it('never serializes the account identifier it carries', function () {
    expect(json_encode(serializedReactivated(TwoFactorStatus::Enabled)))
        ->not->toContain(AccountDeletionFixtures::ACCOUNT_ID);
});
