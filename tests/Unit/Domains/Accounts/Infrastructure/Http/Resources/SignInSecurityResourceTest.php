<?php

declare(strict_types=1);

use App\Domains\Accounts\Application\Dtos\SignInSecurityData;
use App\Domains\Accounts\Infrastructure\Http\Resources\SignInSecurityResource;
use App\Domains\Accounts\ValueObjects\TwoFactorStatus;
use Tests\Support\Accounts\SignInSecurityFixtures;
use Tests\TestCase;

uses(TestCase::class);

/**
 * @return array<string, mixed>
 */
function serializedSignInSecurity(SignInSecurityData $security): array
{
    return SignInSecurityResource::make($security)->response()->getData(true);
}

it('serializes the sign-in security of an account, wrapped in data', function () {
    $security = new SignInSecurityData(
        hasPassword: true,
        twoFactor: TwoFactorStatus::Enabled,
        passkeys: [SignInSecurityFixtures::passkeyData()],
    );

    expect(serializedSignInSecurity($security))->toBe(['data' => [
        'has_password' => true,
        'two_factor' => 'enabled',
        'passkeys' => [[
            'id' => SignInSecurityFixtures::PASSKEY_ID,
            'name' => SignInSecurityFixtures::PASSKEY_NAME,
            'authenticator' => SignInSecurityFixtures::AUTHENTICATOR,
            'created_at' => SignInSecurityFixtures::CREATED_AT,
            'last_used_at' => SignInSecurityFixtures::LAST_USED_AT,
        ]],
    ]]);
});

it('serializes the two-factor status as its wire value', function (TwoFactorStatus $status, string $wire) {
    $security = new SignInSecurityData(hasPassword: false, twoFactor: $status, passkeys: []);

    expect(serializedSignInSecurity($security)['data']['two_factor'])->toBe($wire);
})->with([
    'disabled' => [TwoFactorStatus::Disabled, 'disabled'],
    'pending' => [TwoFactorStatus::Pending, 'pending'],
    'enabled' => [TwoFactorStatus::Enabled, 'enabled'],
]);

it('keeps an unknown authenticator and a passkey never used as explicit nulls', function () {
    $security = new SignInSecurityData(
        hasPassword: false,
        twoFactor: TwoFactorStatus::Disabled,
        passkeys: [SignInSecurityFixtures::passkeyData(authenticator: null, lastUsedAt: null)],
    );

    $passkey = serializedSignInSecurity($security)['data']['passkeys'][0];

    expect($passkey)->toHaveKey('authenticator')
        ->and($passkey['authenticator'])->toBeNull()
        ->and($passkey)->toHaveKey('last_used_at')
        ->and($passkey['last_used_at'])->toBeNull()
        ->and($passkey['created_at'])->toBe(SignInSecurityFixtures::CREATED_AT);
});

it('serializes an account with no passkeys as an empty json list', function () {
    $security = new SignInSecurityData(hasPassword: true, twoFactor: TwoFactorStatus::Disabled, passkeys: []);

    expect(SignInSecurityResource::make($security)->response()->getContent())
        ->toContain('"passkeys":[]');
});

it('exposes exactly the keys the client transcribed, and no credential or owner key on a passkey', function () {
    $security = new SignInSecurityData(
        hasPassword: true,
        twoFactor: TwoFactorStatus::Pending,
        passkeys: [
            SignInSecurityFixtures::passkeyData(),
            SignInSecurityFixtures::passkeyData(id: SignInSecurityFixtures::SECOND_PASSKEY_ID, authenticator: null, lastUsedAt: null),
        ],
    );

    $data = serializedSignInSecurity($security)['data'];

    expect(array_keys($data))->toBe(['has_password', 'two_factor', 'passkeys'])
        ->and(array_map(array_keys(...), $data['passkeys']))->toBe([
            ['id', 'name', 'authenticator', 'created_at', 'last_used_at'],
            ['id', 'name', 'authenticator', 'created_at', 'last_used_at'],
        ]);
});

it('identifies every passkey by its uuid', function () {
    $security = new SignInSecurityData(
        hasPassword: true,
        twoFactor: TwoFactorStatus::Enabled,
        passkeys: [
            SignInSecurityFixtures::passkeyData(),
            SignInSecurityFixtures::passkeyData(id: SignInSecurityFixtures::SECOND_PASSKEY_ID),
        ],
    );

    expect(array_column(serializedSignInSecurity($security)['data']['passkeys'], 'id'))
        ->each->toBeString()->toBeUuid();
});
