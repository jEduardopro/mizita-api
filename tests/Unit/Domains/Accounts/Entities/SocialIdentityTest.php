<?php

declare(strict_types=1);

use App\Domains\Accounts\Entities\SocialIdentity;
use App\Domains\Accounts\Exceptions\InvalidProviderUserId;
use App\Domains\Accounts\ValueObjects\SocialProvider;

describe('link', function () {
    it('links a provider user to an account at the given instant', function () {
        $now = new DateTimeImmutable('2026-01-01T12:00:00+00:00');

        $identity = SocialIdentity::link(
            id: 'identity-uuid',
            accountId: 'account-uuid',
            provider: SocialProvider::Google,
            providerUserId: '104729183746501928374',
            now: $now,
        );

        expect($identity->id)->toBe('identity-uuid')
            ->and($identity->accountId)->toBe('account-uuid')
            ->and($identity->provider)->toBe(SocialProvider::Google)
            ->and($identity->providerUserId)->toBe('104729183746501928374')
            ->and($identity->linkedAt)->toEqual($now);
    });

    it('trims the surrounding whitespace off the provider subject', function () {
        $identity = SocialIdentity::link(
            'identity-uuid',
            'account-uuid',
            SocialProvider::Google,
            "  104729183746501928374\n",
            new DateTimeImmutable,
        );

        expect($identity->providerUserId)->toBe('104729183746501928374');
    });

    it('leaves the subject otherwise untouched, so it still matches what the provider sends', function (string $sub) {
        // Beyond trimming, a subject is an opaque string: case folding or any
        // reformatting would stop a returning person being recognised.
        $identity = SocialIdentity::link('identity-uuid', 'account-uuid', SocialProvider::Google, $sub, new DateTimeImmutable);

        expect($identity->providerUserId)->toBe($sub);
    })->with([
        'numeric' => '104729183746501928374',
        'mixed case' => 'AbC123xYz',
        'with a dash' => 'sub-000-111',
        'internal space kept' => 'two words',
    ]);

    it('rejects a blank provider subject, which would match every other blank one', function (string $sub) {
        // The guard lives here and not only where a GoogleIdentity is parsed,
        // so no caller can route around it by building an entity directly.
        expect(fn () => SocialIdentity::link('identity-uuid', 'account-uuid', SocialProvider::Google, $sub, new DateTimeImmutable))
            ->toThrow(InvalidProviderUserId::class, 'A provider user id cannot be empty.');
    })->with([
        'empty' => '',
        'spaces' => '   ',
        'tab' => "\t",
        'newline' => "\n",
    ]);
});

describe('restore', function () {
    it('skips the creation-time subject rule, because storage is not a second gate', function (string $sub) {
        $identity = SocialIdentity::restore(
            'identity-uuid',
            'account-uuid',
            SocialProvider::Google,
            $sub,
            new DateTimeImmutable,
        );

        expect($identity->providerUserId)->toBe($sub);
    })->with([
        'empty' => '',
        'spaces' => '   ',
        'untrimmed' => '  104729183746501928374  ',
    ]);

    it('rehydrates a link exactly as it was stored', function () {
        $linkedAt = new DateTimeImmutable('2025-06-01T08:30:00+00:00');

        $identity = SocialIdentity::restore(
            'identity-uuid',
            'account-uuid',
            SocialProvider::Google,
            '104729183746501928374',
            $linkedAt,
        );

        expect($identity->id)->toBe('identity-uuid')
            ->and($identity->accountId)->toBe('account-uuid')
            ->and($identity->provider)->toBe(SocialProvider::Google)
            ->and($identity->providerUserId)->toBe('104729183746501928374')
            ->and($identity->linkedAt)->toEqual($linkedAt);
    });
});

it('is immutable once created', function () {
    // Re-pointing a link at another account would hand one person another
    // person's identity, so every property is readonly.
    $identity = SocialIdentity::link(
        'identity-uuid',
        'account-uuid',
        SocialProvider::Google,
        '104729183746501928374',
        new DateTimeImmutable,
    );

    expect(fn () => $identity->accountId = 'someone-else')->toThrow(Error::class);
});
