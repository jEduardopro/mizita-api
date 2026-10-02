<?php

declare(strict_types=1);

use App\Domains\Platform\Entities\PlatformAdmin;
use App\Domains\Platform\Exceptions\InvalidPlatformAdminName;
use App\Domains\Platform\ValueObjects\PlatformAdminEmail;
use Tests\Support\Platform\ImpersonationFixtures;

function registerPlatformAdminNamed(string $name): PlatformAdmin
{
    return PlatformAdmin::registerWithConfirmedAuthenticator(
        id: ImpersonationFixtures::ADMIN_ID,
        name: $name,
        email: PlatformAdminEmail::fromString('grace@mizita.test'),
        passwordHash: '$2y$04$platform.admin.hash',
        twoFactorSecret: 'JBSWY3DPEHPK3PXP',
        now: ImpersonationFixtures::now(),
    );
}

it('registers an admin with every field it was given', function () {
    $admin = registerPlatformAdminNamed('Grace Hopper');

    expect($admin->id)->toBe(ImpersonationFixtures::ADMIN_ID)
        ->and($admin->id)->toMatch(ImpersonationFixtures::UUID_PATTERN)
        ->and($admin->name)->toBe('Grace Hopper')
        ->and($admin->email->value)->toBe('grace@mizita.test')
        ->and($admin->passwordHash)->toBe('$2y$04$platform.admin.hash')
        ->and($admin->twoFactorSecret)->toBe('JBSWY3DPEHPK3PXP');
});

it('confirms the authenticator app and records the registration at the same instant', function () {
    $admin = registerPlatformAdminNamed('Grace Hopper');

    expect($admin->twoFactorConfirmedAt)->toEqual(ImpersonationFixtures::now())
        ->and($admin->createdAt)->toEqual(ImpersonationFixtures::now());
});

it('trims the name', function () {
    expect(registerPlatformAdminNamed("  Grace Hopper \t")->name)->toBe('Grace Hopper');
});

it('keeps accents and unicode in the name', function () {
    expect(registerPlatformAdminNamed('José Ñúñez Çelik')->name)->toBe('José Ñúñez Çelik');
});

it('accepts a name of exactly the maximum length, counted in characters', function () {
    $name = str_repeat('ñ', PlatformAdmin::MAXIMUM_NAME_LENGTH);

    expect(registerPlatformAdminNamed($name)->name)->toBe($name);
});

it('rejects a name with nothing in it', function (string $name) {
    expect(fn () => registerPlatformAdminNamed($name))
        ->toThrow(InvalidPlatformAdminName::class, 'A platform admin name cannot be empty.');
})->with([
    'empty' => [''],
    'spaces' => ['   '],
    'a tab and a newline' => ["\t\n"],
]);

it('rejects a name past the maximum length', function () {
    expect(fn () => registerPlatformAdminNamed(str_repeat('a', PlatformAdmin::MAXIMUM_NAME_LENGTH + 1)))
        ->toThrow(InvalidPlatformAdminName::class, 'A platform admin name takes up to [255] characters.');
});

it('measures the maximum length after trimming', function () {
    $name = '  '.str_repeat('a', PlatformAdmin::MAXIMUM_NAME_LENGTH).'  ';

    expect(registerPlatformAdminNamed($name)->name)->toBe(str_repeat('a', PlatformAdmin::MAXIMUM_NAME_LENGTH));
});
