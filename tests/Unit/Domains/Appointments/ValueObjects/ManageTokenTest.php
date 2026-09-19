<?php

declare(strict_types=1);

use App\Domains\Appointments\Exceptions\InvalidManageToken;
use App\Domains\Appointments\ValueObjects\ManageToken;
use App\Shared\ValueObjects\DomainFailureKind;

const MANAGE_TOKEN = 'a1b2c3d4e5f6071829304a5b6c7d8e9fa1b2c3d4e5f6071829304a5b6c7d8e9f';

const OTHER_MANAGE_TOKEN = 'f9e8d7c6b5a4039281706f5e4d3c2b1af9e8d7c6b5a4039281706f5e4d3c2b1a';

it('carries a credential of thirty two bytes written in hexadecimal', function () {
    expect(ManageToken::BYTE_LENGTH)->toBe(32)
        ->and(strlen(MANAGE_TOKEN))->toBe(64)
        ->and(ManageToken::fromString(MANAGE_TOKEN)->value)->toBe(MANAGE_TOKEN);
});

it('refuses a credential that is not sixty four hexadecimal characters', function (string $value) {
    expect(fn () => ManageToken::fromString($value))->toThrow(InvalidManageToken::class);
})->with([
    'empty' => '',
    'one character short' => 'a1b2c3d4e5f6071829304a5b6c7d8e9fa1b2c3d4e5f6071829304a5b6c7d8e9',
    'one character long' => 'a1b2c3d4e5f6071829304a5b6c7d8e9fa1b2c3d4e5f6071829304a5b6c7d8e9fa',
    'not hexadecimal' => 'z1b2c3d4e5f6071829304a5b6c7d8e9fa1b2c3d4e5f6071829304a5b6c7d8e9f',
    'a uuid' => '01930000-0000-7000-8000-0000000000a1',
]);

it('refuses as a domain failure the responder can classify', function () {
    try {
        ManageToken::fromString('nope');
        $thrown = null;
    } catch (InvalidManageToken $refusal) {
        $thrown = $refusal;
    }

    expect($thrown?->errorCode())->toBe('invalid_manage_token')
        ->and($thrown?->kind())->toBe(DomainFailureKind::Invalid);
});

it('hashes the same credential to the same digest every time', function () {
    expect(ManageToken::fromString(MANAGE_TOKEN)->hash())
        ->toBe(ManageToken::fromString(MANAGE_TOKEN)->hash());
});

it('hashes two different credentials to two different digests', function () {
    expect(ManageToken::fromString(MANAGE_TOKEN)->hash())
        ->not->toBe(ManageToken::fromString(OTHER_MANAGE_TOKEN)->hash());
});

it('never hands the credential itself to the column', function () {
    $hash = ManageToken::fromString(MANAGE_TOKEN)->hash();

    expect($hash)->not->toBe(MANAGE_TOKEN)
        ->and(strlen($hash))->toBe(64)
        ->and(ctype_xdigit($hash))->toBeTrue();
});

it('accepts the credential the stored digest was made from', function () {
    expect(ManageToken::matches(MANAGE_TOKEN, ManageToken::fromString(MANAGE_TOKEN)->hash()))->toBeTrue();
});

it('rejects a credential the stored digest was not made from', function (string $candidate) {
    expect(ManageToken::matches($candidate, ManageToken::fromString(MANAGE_TOKEN)->hash()))->toBeFalse();
})->with([
    'another token' => OTHER_MANAGE_TOKEN,
    'the same token uppercased' => strtoupper(MANAGE_TOKEN),
    'the token with a character changed' => 'b1b2c3d4e5f6071829304a5b6c7d8e9fa1b2c3d4e5f6071829304a5b6c7d8e9f',
    'nothing at all' => '',
    'the token with its last character cut off' => 'a1b2c3d4e5f6071829304a5b6c7d8e9fa1b2c3d4e5f6071829304a5b6c7d8e9',
]);

it('rejects every candidate when the stored digest is empty', function () {
    expect(ManageToken::matches(MANAGE_TOKEN, ''))->toBeFalse();
});
