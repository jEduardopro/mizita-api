<?php

declare(strict_types=1);

use App\Domains\Accounts\Application\Dtos\PreviewAccountDeletionInput;
use App\Domains\Accounts\Exceptions\AccountNotFound;
use Tests\Support\Accounts\AccountDeletionFixtures;

it('accepts an account uuid', function (string $accountId) {
    expect(fn () => (new PreviewAccountDeletionInput($accountId))->validate())->not->toThrow(Throwable::class);
})->with([
    'lower case' => AccountDeletionFixtures::ACCOUNT_ID,
    'upper case' => '01930000-0000-7000-8000-00000000AC01',
]);

it('refuses anything that is not a uuid as an account that does not exist', function (string $accountId) {
    expect(fn () => (new PreviewAccountDeletionInput($accountId))->validate())->toThrow(AccountNotFound::class);
})->with([
    'empty' => '',
    'whitespace' => '   ',
    'an internal integer key' => '7',
    'a slug' => 'account-uuid',
    'a uuid with a trailing newline' => AccountDeletionFixtures::ACCOUNT_ID."\n",
]);
