<?php

declare(strict_types=1);

use App\Domains\Accounts\Infrastructure\Eloquent\Mappers\PasskeyMapper;
use App\Domains\Accounts\Infrastructure\Eloquent\Models\PasskeyModel;
use Tests\Support\Accounts\SignInSecurityFixtures;

/**
 * @param  array<string, mixed>  $attributes
 */
function storedPasskey(array $attributes = []): PasskeyModel
{
    return (new PasskeyModel)->setRawAttributes([
        'id' => 42,
        'uuid' => SignInSecurityFixtures::PASSKEY_ID,
        'user_id' => 7,
        'name' => SignInSecurityFixtures::PASSKEY_NAME,
        'credential_id' => 'credential-id',
        'credential' => json_encode(['aaguid' => 'ea9b8d66-4d01-1d21-3ce4-b6b48cb575d4', 'publicKey' => 'secret-key-material']),
        'created_at' => new DateTimeImmutable(SignInSecurityFixtures::CREATED_AT),
        'last_used_at' => null,
        ...$attributes,
    ], sync: true);
}

beforeEach(function () {
    $this->mapper = new PasskeyMapper;
});

it('reads a stored passkey field by field, identified by its uuid rather than its internal key', function () {
    $passkey = $this->mapper->toRegisteredPasskey(storedPasskey([
        'last_used_at' => new DateTimeImmutable(SignInSecurityFixtures::LAST_USED_AT),
    ]));

    expect($passkey->id)->toBe(SignInSecurityFixtures::PASSKEY_ID)
        ->and($passkey->name)->toBe(SignInSecurityFixtures::PASSKEY_NAME)
        ->and($passkey->authenticator)->toBe('Google Password Manager')
        ->and($passkey->createdAt->format(DATE_ATOM))->toBe(SignInSecurityFixtures::CREATED_AT)
        ->and($passkey->lastUsedAt->format(DATE_ATOM))->toBe(SignInSecurityFixtures::LAST_USED_AT);
});

it('reads a passkey that was never used to sign in with no last use', function () {
    expect($this->mapper->toRegisteredPasskey(storedPasskey())->lastUsedAt)->toBeNull();
});

it('hands the domain plain DateTimeImmutable instances, never Carbon', function () {
    $passkey = $this->mapper->toRegisteredPasskey(storedPasskey([
        'last_used_at' => new DateTimeImmutable(SignInSecurityFixtures::LAST_USED_AT),
    ]));

    expect(get_class($passkey->createdAt))->toBe(DateTimeImmutable::class)
        ->and(get_class($passkey->lastUsedAt))->toBe(DateTimeImmutable::class);
});

it('names no authenticator the AAGUID cannot identify', function (array $credential) {
    expect($this->mapper->toRegisteredPasskey(storedPasskey(['credential' => json_encode($credential)]))->authenticator)
        ->toBeNull();
})->with([
    'the all-zero unknown AAGUID' => [['aaguid' => '00000000-0000-0000-0000-000000000000']],
    'an AAGUID missing from the catalogue' => [['aaguid' => '11111111-2222-3333-4444-555555555555']],
    'no AAGUID at all' => [[]],
]);

it('keeps the name exactly as the person typed it', function (string $name) {
    expect($this->mapper->toRegisteredPasskey(storedPasskey(['name' => $name]))->name)->toBe($name);
})->with([
    'accents' => 'Teléfono de Muñoz',
    'cjk' => '愛田のiPhone',
    'emoji' => 'YubiKey 🔑',
]);
