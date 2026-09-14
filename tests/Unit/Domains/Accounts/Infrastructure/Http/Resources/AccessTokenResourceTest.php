<?php

declare(strict_types=1);

use App\Domains\Accounts\Application\Dtos\AuthenticatedAccountData;
use App\Domains\Accounts\Infrastructure\Http\Resources\AccessTokenResource;
use Tests\Support\Accounts\GoogleFixtures;
use Tests\TestCase;

uses(TestCase::class);

function authenticatedAccount(
    string $id = 'account-uuid',
    string $name = 'Ada Lovelace',
    string $email = 'ada@example.com',
    bool $isNew = false,
): AuthenticatedAccountData {
    $account = GoogleFixtures::storedAccount(id: $id, name: $name, email: $email);

    return $isNew
        ? AuthenticatedAccountData::forNewAccount($account)
        : AuthenticatedAccountData::forExistingAccount($account);
}

/**
 * @return array<string, mixed>
 */
function serializedAccessToken(AuthenticatedAccountData $account, string $token = 'plain-text-token'): array
{
    return (array) AccessTokenResource::make($account, $token)->response()->getData(true)['data'];
}

it('serializes the token beside the account the client transcribed', function () {
    expect(serializedAccessToken(authenticatedAccount()))->toBe([
        'token' => 'plain-text-token',
        'account' => [
            'id' => 'account-uuid',
            'name' => 'Ada Lovelace',
            'email' => 'ada@example.com',
        ],
    ]);
});

it('wraps the payload in the data envelope', function () {
    expect(AccessTokenResource::make(authenticatedAccount(), 'plain-text-token')->response()->getData(true))
        ->toBe(['data' => [
            'token' => 'plain-text-token',
            'account' => [
                'id' => 'account-uuid',
                'name' => 'Ada Lovelace',
                'email' => 'ada@example.com',
            ],
        ]]);
});

it('exposes the account uuid as the id, never an internal key', function () {
    expect(serializedAccessToken(authenticatedAccount(id: '01930000-0000-7000-8000-000000000001'))['account']['id'])
        ->toBe('01930000-0000-7000-8000-000000000001')
        ->toBeString();
});

it('keeps the new account flag off the wire, whichever way the account arrived', function (bool $isNew) {
    $payload = serializedAccessToken(authenticatedAccount(isNew: $isNew));

    expect($payload['account'])->not->toHaveKey('isNewAccount')
        ->and($payload['account'])->not->toHaveKey('is_new_account')
        ->and($payload['account'])->toBe([
            'id' => 'account-uuid',
            'name' => 'Ada Lovelace',
            'email' => 'ada@example.com',
        ]);
})->with(['returning' => false, 'newly registered' => true]);

it('never serializes business_id, at either level', function () {
    $payload = serializedAccessToken(authenticatedAccount());

    expect($payload)->not->toHaveKey('business_id')
        ->and($payload['account'])->not->toHaveKey('business_id');
});

it('does not nest a second data envelope around the account', function () {
    expect(serializedAccessToken(authenticatedAccount())['account'])->not->toHaveKey('data');
});
