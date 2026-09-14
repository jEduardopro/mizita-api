<?php

declare(strict_types=1);

use App\Domains\Accounts\Entities\SocialIdentity;
use App\Domains\Accounts\Infrastructure\Eloquent\Mappers\SocialIdentityMapper;
use App\Domains\Accounts\ValueObjects\SocialProvider;

beforeEach(function () {
    $this->mapper = new SocialIdentityMapper;
});

it('writes the uuid, the account key, the provider and the provider user id', function () {
    $identity = SocialIdentity::restore(
        'identity-uuid',
        'account-uuid',
        SocialProvider::Google,
        '104729183746501928374',
        new DateTimeImmutable('2026-01-01 12:00:00'),
    );

    expect($this->mapper->toAttributes($identity, accountKey: 42))->toBe([
        'uuid' => 'identity-uuid',
        'account_id' => 42,
        'provider' => SocialProvider::Google,
        'provider_user_id' => '104729183746501928374',
    ]);
});

it('joins on the account int key, not on the account uuid the entity carries', function () {
    $identity = SocialIdentity::restore(
        'identity-uuid',
        'account-uuid',
        SocialProvider::Google,
        '104729183746501928374',
        new DateTimeImmutable,
    );

    $attributes = $this->mapper->toAttributes($identity, accountKey: 7);

    expect($attributes['account_id'])->toBe(7)
        ->and($attributes)->not->toContain('account-uuid');
});

it('hands the provider over as the enum, so an unknown string cannot reach the column', function () {
    $identity = SocialIdentity::restore(
        'identity-uuid',
        'account-uuid',
        SocialProvider::Google,
        '104729183746501928374',
        new DateTimeImmutable,
    );

    expect($this->mapper->toAttributes($identity, 42)['provider'])->toBe(SocialProvider::Google);
});

it('writes no attribute the SocialIdentity entity does not hold', function () {
    $identity = SocialIdentity::restore(
        'identity-uuid',
        'account-uuid',
        SocialProvider::Google,
        '104729183746501928374',
        new DateTimeImmutable,
    );

    expect(array_keys($this->mapper->toAttributes($identity, 42)))
        ->toBe(['uuid', 'account_id', 'provider', 'provider_user_id']);
});
