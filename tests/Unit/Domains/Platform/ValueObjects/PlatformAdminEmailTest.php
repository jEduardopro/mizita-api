<?php

declare(strict_types=1);

use App\Domains\Platform\Exceptions\InvalidPlatformAdminEmail;
use App\Domains\Platform\ValueObjects\PlatformAdminEmail;
use App\Shared\Contracts\DomainFailure;

function platformAdminEmailOfLength(int $length): string
{
    $local = str_repeat('a', 64);
    $topLevel = '.test';
    $remaining = $length - strlen($local) - 1 - strlen($topLevel);
    $labels = [];

    while ($remaining > 0) {
        $label = min(60, $remaining);
        $label -= $remaining - $label === 1 ? 1 : 0;
        $labels[] = str_repeat('b', $label);
        $remaining -= $label + 1;
    }

    return $local.'@'.implode('.', $labels).$topLevel;
}

it('keeps a well formed address', function () {
    expect(PlatformAdminEmail::fromString('grace@mizita.test')->value)->toBe('grace@mizita.test');
});

it('trims and lowercases the address so one admin cannot be registered twice by casing', function () {
    expect(PlatformAdminEmail::fromString("  Grace.Hopper@Mizita.TEST \t")->value)->toBe('grace.hopper@mizita.test');
});

it('accepts the longest address an email validator lets through', function () {
    $email = platformAdminEmailOfLength(254);

    expect(strlen($email))->toBe(254)
        ->and(PlatformAdminEmail::fromString($email)->value)->toBe($email);
});

it('rejects an address that is not one', function (string $email) {
    expect(fn () => PlatformAdminEmail::fromString($email))->toThrow(InvalidPlatformAdminEmail::class);
})->with([
    'empty' => [''],
    'spaces only' => ['   '],
    'a tab and a newline' => ["\t\n"],
    'no at sign' => ['grace.mizita.test'],
    'no domain' => ['grace@'],
    'no local part' => ['@mizita.test'],
    'two at signs' => ['grace@@mizita.test'],
    'an inner space' => ['grace hopper@mizita.test'],
    'an accented local part' => ['grâce@mizita.test'],
    'past the maximum length' => [platformAdminEmailOfLength(256)],
]);

it('says the address is too long before judging its shape', function () {
    expect(fn () => PlatformAdminEmail::fromString(platformAdminEmailOfLength(256)))
        ->toThrow(InvalidPlatformAdminEmail::class, 'A platform admin email takes up to [255] characters.');
});

it('refuses with a domain failure the edge can render', function () {
    try {
        PlatformAdminEmail::fromString('not-an-email');
    } catch (InvalidPlatformAdminEmail $failure) {
        expect($failure)->toBeInstanceOf(DomainFailure::class)
            ->and($failure->errorCode())->toBe('invalid_platform_admin_email');

        return;
    }

    test()->fail('A malformed address was accepted.');
});
