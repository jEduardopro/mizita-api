<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;
use Laravel\Fortify\Fortify;
use Tests\TestCase;

uses(TestCase::class);

const PASSWORD_CONFIRMATION_MIDDLEWARE = 'password.confirm';

/**
 * @return list<string>
 */
function middlewareOfRoute(string $name): array
{
    return Route::getRoutes()->getByName($name)->gatherMiddleware();
}

describe('two factor authentication', function () {
    it('is enabled', function () {
        expect(Features::canManageTwoFactorAuthentication())->toBeTrue();
    });

    it('asks for a code before the secret counts as enabled', function () {
        expect(Features::optionEnabled(Features::twoFactorAuthentication(), 'confirm'))->toBeTrue()
            ->and(Fortify::confirmsTwoFactorAuthentication())->toBeTrue();
    });

    it('asks for the password again before it can be managed', function () {
        expect(Features::optionEnabled(Features::twoFactorAuthentication(), 'confirmPassword'))->toBeTrue();
    });

    it('guards every management route behind a fresh password', function (string $route) {
        expect(middlewareOfRoute($route))->toContain(PASSWORD_CONFIRMATION_MIDDLEWARE);
    })->with([
        'two-factor.enable',
        'two-factor.confirm',
        'two-factor.disable',
        'two-factor.qr-code',
        'two-factor.secret-key',
        'two-factor.regenerate-recovery-codes',
    ]);

    it('throttles the challenge', function () {
        expect(middlewareOfRoute('two-factor.login.store'))->toContain('throttle:two-factor');
    });
});

describe('passkeys', function () {
    it('are enabled', function () {
        expect(Features::canManagePasskeys())->toBeTrue();
    });

    it('ask for the password again before they can be managed', function () {
        expect(Features::optionEnabled(Features::passkeys(), 'confirmPassword'))->toBeTrue()
            ->and(config('passkeys.management_middleware'))->toBe([PASSWORD_CONFIRMATION_MIDDLEWARE]);
    });

    it('guard every management route behind a fresh password', function (string $route) {
        expect(middlewareOfRoute($route))->toContain(PASSWORD_CONFIRMATION_MIDDLEWARE);
    })->with([
        'passkey.registration-options',
        'passkey.store',
        'passkey.destroy',
    ]);

    it('let a guest sign in without a password', function (string $route) {
        expect(middlewareOfRoute($route))->not->toContain(PASSWORD_CONFIRMATION_MIDDLEWARE)
            ->toContain('throttle:passkeys');
    })->with([
        'passkey.login-options',
        'passkey.login',
    ]);
});
