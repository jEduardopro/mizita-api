<?php

declare(strict_types=1);

use App\Domains\Platform\Application\Dtos\PlatformAdminData;
use App\Domains\Platform\Application\Dtos\RegisterPlatformAdminInput;
use App\Domains\Platform\Application\UseCases\RegisterPlatformAdmin;
use App\Domains\Platform\Exceptions\PlatformAdminAlreadyExists;
use App\Shared\ValueObjects\DomainFailureKind;
use Tests\Support\FakeClock;
use Tests\Support\FixedIdGenerator;
use Tests\Support\Platform\FakeAuthenticatorApp;
use Tests\Support\Platform\FakePasswordHasher;
use Tests\Support\Platform\FakePlatformAdminRepository;
use Tests\Support\Platform\ImpersonationFixtures;

const REGISTER_ADMIN_SECRET = 'JBSWY3DPEHPK3PXP';

const REGISTER_ADMIN_CODE = '492039';

const REGISTER_ADMIN_PASSWORD = 'correct-horse-battery';

beforeEach(function () {
    $this->admins = new FakePlatformAdminRepository;
    $this->passwords = new FakePasswordHasher;
    $this->authenticatorApp = new FakeAuthenticatorApp([REGISTER_ADMIN_SECRET => REGISTER_ADMIN_CODE]);
    $this->clock = new FakeClock(ImpersonationFixtures::now());

    $this->register = fn (array $overrides = []) => (new RegisterPlatformAdmin(
        $this->admins,
        $this->passwords,
        $this->authenticatorApp,
        new FixedIdGenerator(ImpersonationFixtures::ADMIN_ID),
        $this->clock,
    ))->handle(new RegisterPlatformAdminInput(...[
        'email' => 'grace@mizita.test',
        'name' => 'Grace Hopper',
        'password' => REGISTER_ADMIN_PASSWORD,
        'twoFactorSecret' => REGISTER_ADMIN_SECRET,
        'confirmationCode' => REGISTER_ADMIN_CODE,
        ...$overrides,
    ]));
});

describe('registering', function () {
    it('answers with the new admin, field by field', function () {
        $data = ($this->register)()->value();

        expect($data)->toBeInstanceOf(PlatformAdminData::class)
            ->and($data->id)->toBe(ImpersonationFixtures::ADMIN_ID)
            ->and($data->name)->toBe('Grace Hopper')
            ->and($data->email)->toBe('grace@mizita.test');
    });

    it('hands the admin back under a uuid from the id generator', function () {
        expect(($this->register)()->value()->id)->toMatch(ImpersonationFixtures::UUID_PATTERN);
    });

    it('saves exactly one admin', function () {
        ($this->register)();

        expect($this->admins->saved)->toHaveCount(1)
            ->and($this->admins->saved[0]->id)->toBe(ImpersonationFixtures::ADMIN_ID);
    });

    it('saves the hash of the password, never the password itself', function () {
        ($this->register)();

        expect($this->passwords->hashed)->toBe([REGISTER_ADMIN_PASSWORD])
            ->and($this->admins->saved[0]->passwordHash)->toBe(FakePasswordHasher::PREFIX.REGISTER_ADMIN_PASSWORD)
            ->and($this->admins->saved[0]->passwordHash)->not->toBe(REGISTER_ADMIN_PASSWORD);
    });

    it('saves the authenticator secret as confirmed at the instant the clock reads', function () {
        $this->clock->advance('PT5M');

        ($this->register)();

        expect($this->admins->saved[0]->twoFactorSecret)->toBe(REGISTER_ADMIN_SECRET)
            ->and($this->admins->saved[0]->twoFactorConfirmedAt)->toEqual(new DateTimeImmutable('2026-09-25T15:05:00+00:00'))
            ->and($this->admins->saved[0]->createdAt)->toEqual(new DateTimeImmutable('2026-09-25T15:05:00+00:00'));
    });

    it('checks the code against the secret being enrolled', function () {
        ($this->register)();

        expect($this->authenticatorApp->checked)->toBe([[REGISTER_ADMIN_SECRET, REGISTER_ADMIN_CODE]]);
    });

    it('normalizes the email and trims the name before saving', function () {
        $data = ($this->register)(['email' => '  Grace@Mizita.TEST ', 'name' => '  Grace Hopper  '])->value();

        expect($data->email)->toBe('grace@mizita.test')
            ->and($data->name)->toBe('Grace Hopper')
            ->and($this->admins->saved[0]->email->value)->toBe('grace@mizita.test')
            ->and($this->admins->emailsAsked)->toBe(['grace@mizita.test']);
    });

    it('accepts a password of exactly the minimum length', function () {
        $password = str_repeat('p', RegisterPlatformAdminInput::MINIMUM_PASSWORD_LENGTH);

        $response = ($this->register)(['password' => $password]);

        expect($response->succeeded())->toBeTrue()
            ->and($this->admins->saved[0]->passwordHash)->toBe(FakePasswordHasher::PREFIX.$password);
    });
});

describe('refusing an input that does not hold up', function () {
    it('refuses it and touches no port', function (array $overrides, string $code) {
        $response = ($this->register)($overrides);

        expect($response->failed())->toBeTrue()
            ->and($response->error()->code)->toBe($code)
            ->and($response->error()->kind)->toBe(DomainFailureKind::Invalid)
            ->and($this->admins->emailsAsked)->toBe([])
            ->and($this->admins->saved)->toBe([])
            ->and($this->passwords->hashed)->toBe([])
            ->and($this->authenticatorApp->checked)->toBe([]);
    })->with([
        'a malformed email' => [['email' => 'grace.mizita.test'], 'invalid_platform_admin_email'],
        'a blank email' => [['email' => '   '], 'invalid_platform_admin_email'],
        'a blank name' => [['name' => '   '], 'invalid_platform_admin_name'],
        'a name past the maximum length' => [['name' => str_repeat('a', 256)], 'invalid_platform_admin_name'],
        'a password one character short' => [['password' => str_repeat('p', RegisterPlatformAdminInput::MINIMUM_PASSWORD_LENGTH - 1)], 'platform_admin_password_too_short'],
        'a malformed code' => [['confirmationCode' => '12345'], 'invalid_platform_two_factor_code'],
    ]);
});

describe('refusing an enrollment the authenticator app did not confirm', function () {
    it('refuses a well formed code that does not verify, and saves nothing', function () {
        $response = ($this->register)(['confirmationCode' => '000000']);

        expect($response->failed())->toBeTrue()
            ->and($response->error()->code)->toBe('invalid_platform_two_factor_code')
            ->and($response->error()->kind)->toBe(DomainFailureKind::Invalid)
            ->and($this->admins->saved)->toBe([])
            ->and($this->passwords->hashed)->toBe([]);
    });

    it('refuses a code that verifies against another secret', function () {
        $response = ($this->register)(['twoFactorSecret' => 'KRSXG5CTMVRXEZLU']);

        expect($response->failed())->toBeTrue()
            ->and($response->error()->code)->toBe('invalid_platform_two_factor_code')
            ->and($this->admins->saved)->toBe([]);
    });
});

describe('refusing a second admin with the same email', function () {
    it('refuses it as a conflict, before checking the code or hashing the password', function () {
        $this->admins = new FakePlatformAdminRepository('grace@mizita.test');

        $response = ($this->register)();

        expect($response->failed())->toBeTrue()
            ->and($response->error()->code)->toBe('platform_admin_already_exists')
            ->and($response->error()->kind)->toBe(DomainFailureKind::Conflict)
            ->and($this->admins->saved)->toBe([])
            ->and($this->authenticatorApp->checked)->toBe([])
            ->and($this->passwords->hashed)->toBe([]);
    });

    it('finds the existing admin whatever casing the email was typed in', function () {
        $this->admins = new FakePlatformAdminRepository('grace@mizita.test');

        $response = ($this->register)(['email' => 'GRACE@Mizita.Test']);

        expect($response->error()->code)->toBe('platform_admin_already_exists');
    });

    it('returns the conflict the repository raises when another registration won the race', function () {
        $this->admins->refusingToSaveWith(PlatformAdminAlreadyExists::withEmail('grace@mizita.test'));

        $response = ($this->register)();

        expect($response->failed())->toBeTrue()
            ->and($response->error()->code)->toBe('platform_admin_already_exists')
            ->and($response->error()->kind)->toBe(DomainFailureKind::Conflict);
    });

    it('lets a failure that is no refusal escape to the caller', function () {
        $this->admins->refusingToSaveWith(new RuntimeException('connection lost'));

        expect(fn () => ($this->register)())->toThrow(RuntimeException::class, 'connection lost');
    });
});
