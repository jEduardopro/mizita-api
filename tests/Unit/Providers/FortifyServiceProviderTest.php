<?php

declare(strict_types=1);

use App\Domains\Accounts\Infrastructure\Eloquent\Models\PasskeyModel;
use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\RateLimiter;
use Laravel\Fortify\Contracts\ConfirmPasswordViewResponse;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\Contracts\TwoFactorChallengeViewResponse;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Passkeys\Passkeys;
use Tests\TestCase;

uses(TestCase::class);

function inertiaVisit(string $path): Request
{
    $request = Request::create($path, 'GET', server: ['HTTP_X_INERTIA' => 'true']);
    $request->setLaravelSession(app('session.store'));

    return $request;
}

function passkeyOwnedBy(mixed $owner): PasskeyModel
{
    return (new PasskeyModel)->setRelation('user', $owner);
}

function passkeyLoginRequest(): Request
{
    return Request::create('/passkeys/login', 'POST');
}

/**
 * @return list<Limit>
 */
function fortifyLimitsFor(string $limiter, Request $request): array
{
    $limits = RateLimiter::limiter($limiter)($request);

    return is_array($limits) ? array_values($limits) : [$limits];
}

/**
 * @param  array<string, mixed>  $payload
 */
function throttledPost(string $path, array $payload = [], string $ip = '203.0.113.10'): Request
{
    $request = Request::create($path, 'POST', $payload, server: ['REMOTE_ADDR' => $ip]);
    $request->setLaravelSession(app('session.store'));

    return $request;
}

/**
 * @return array{maxAttempts: int, decaySeconds: int, key: mixed}
 */
function limitShape(Limit $limit): array
{
    return ['maxAttempts' => $limit->maxAttempts, 'decaySeconds' => $limit->decaySeconds, 'key' => $limit->key];
}

/**
 * @return list<string>
 */
function fortifyRouteMiddleware(string $name): array
{
    return app(Router::class)->getRoutes()->getByName($name)->gatherMiddleware();
}

describe('the views fortify renders', function () {
    it('renders a view contract as its inertia page', function (string $contract, string $path, string $component) {
        $response = $this->app->make($contract)->toResponse(inertiaVisit($path));

        expect($response)->toBeInstanceOf(JsonResponse::class)
            ->and($response->getData(true)['component'])->toBe($component);
    })->with([
        'the two factor challenge' => [TwoFactorChallengeViewResponse::class, '/two-factor-challenge', 'auth/two-factor-challenge'],
        'the password confirmation' => [ConfirmPasswordViewResponse::class, '/user/confirm-password', 'auth/confirm-password'],
    ]);
});

describe('who may sign in with a passkey', function () {
    it('lets an active account in', function () {
        expect(Passkeys::allowsLogin(passkeyLoginRequest(), passkeyOwnedBy(new User)))->toBeTrue();
    });

    it('turns away a passkey whose owner no longer resolves', function () {
        expect(Passkeys::allowsLogin(passkeyLoginRequest(), passkeyOwnedBy(null)))->toBeFalse();
    });

    it('turns away an account that was soft deleted', function () {
        $deleted = (new User)->forceFill(['deleted_at' => '2026-09-01 10:00:00']);

        expect(Passkeys::allowsLogin(passkeyLoginRequest(), passkeyOwnedBy($deleted)))->toBeFalse();
    });

    it('turns away a passkey user that is not an application account', function () {
        $stranger = new class extends Authenticatable implements PasskeyUser
        {
            use PasskeyAuthenticatable;
        };

        expect(Passkeys::allowsLogin(passkeyLoginRequest(), passkeyOwnedBy($stranger)))->toBeFalse();
    });
});

describe('the two factor challenge limiter', function () {
    it('allows five attempts a minute', function () {
        $request = inertiaVisit('/two-factor-challenge');
        $request->session()->put('login.id', 7);

        expect(fortifyLimitsFor('two-factor', $request)[0]->maxAttempts)->toBe(5);
    });

    it('counts each pending sign in on its own', function () {
        $first = inertiaVisit('/two-factor-challenge');
        $first->session()->put('login.id', 7);
        $firstKey = fortifyLimitsFor('two-factor', $first)[0]->key;

        $second = inertiaVisit('/two-factor-challenge');
        $second->session()->put('login.id', 8);

        expect($firstKey)->not->toBe(fortifyLimitsFor('two-factor', $second)[0]->key);
    });
});

describe('the sign in limiter', function () {
    it('allows five attempts a minute per address and twenty a minute per IP', function () {
        $limits = fortifyLimitsFor('login', throttledPost('/login', ['email' => 'ada@example.com']));

        expect(array_map(limitShape(...), $limits))->toBe([
            ['maxAttempts' => 5, 'decaySeconds' => 60, 'key' => 'ada@example.com|203.0.113.10'],
            ['maxAttempts' => 20, 'decaySeconds' => 60, 'key' => 'ip:203.0.113.10'],
        ]);
    });

    it('counts one address the same way however it is cased', function () {
        $lower = fortifyLimitsFor('login', throttledPost('/login', ['email' => 'ada@example.com']));
        $mixed = fortifyLimitsFor('login', throttledPost('/login', ['email' => 'Ada@Example.COM']));

        expect($mixed[0]->key)->toBe($lower[0]->key);
    });

    it('charges every address tried from one IP to the same IP counter', function () {
        $ada = fortifyLimitsFor('login', throttledPost('/login', ['email' => 'ada@example.com']));
        $grace = fortifyLimitsFor('login', throttledPost('/login', ['email' => 'grace@example.com']));

        expect($ada[0]->key)->not->toBe($grace[0]->key)
            ->and($ada[1]->key)->toBe($grace[1]->key);
    });

    it('keeps the counters of two IPs apart', function () {
        $first = fortifyLimitsFor('login', throttledPost('/login', ['email' => 'ada@example.com'], '203.0.113.10'));
        $second = fortifyLimitsFor('login', throttledPost('/login', ['email' => 'ada@example.com'], '198.51.100.7'));

        expect($first[0]->key)->not->toBe($second[0]->key)
            ->and($first[1]->key)->not->toBe($second[1]->key);
    });
});

describe('the passkey limiter', function () {
    it('allows ten attempts a minute per credential and twenty a minute per IP', function () {
        $limits = fortifyLimitsFor('passkeys', throttledPost('/passkeys/login', ['credential' => ['id' => 'credential-a']]));

        expect(array_map(limitShape(...), $limits))->toBe([
            ['maxAttempts' => 10, 'decaySeconds' => 60, 'key' => 'credential-a|203.0.113.10'],
            ['maxAttempts' => 20, 'decaySeconds' => 60, 'key' => 'ip:203.0.113.10'],
        ]);
    });

    it('allows ten attempts a minute before a credential is chosen', function () {
        expect(fortifyLimitsFor('passkeys', inertiaVisit('/passkeys/login/options'))[0]->maxAttempts)->toBe(10);
    });

    it('counts each credential on its own', function () {
        $first = throttledPost('/passkeys/login', ['credential' => ['id' => 'credential-a']]);
        $second = throttledPost('/passkeys/login', ['credential' => ['id' => 'credential-b']]);

        expect(fortifyLimitsFor('passkeys', $first)[0]->key)->not->toBe(fortifyLimitsFor('passkeys', $second)[0]->key);
    });

    it('charges every credential tried from one IP to the same IP counter', function () {
        $first = throttledPost('/passkeys/login', ['credential' => ['id' => 'credential-a']]);
        $second = throttledPost('/passkeys/login', ['credential' => ['id' => 'credential-b']]);

        expect(fortifyLimitsFor('passkeys', $first)[1]->key)->toBe(fortifyLimitsFor('passkeys', $second)[1]->key);
    });
});

describe('the registration limiter', function () {
    it('allows five sign ups a minute and twenty an hour per IP', function () {
        expect(array_map(limitShape(...), fortifyLimitsFor('register', throttledPost('/register'))))->toBe([
            ['maxAttempts' => 5, 'decaySeconds' => 60, 'key' => 'ip-minute:203.0.113.10'],
            ['maxAttempts' => 20, 'decaySeconds' => 3600, 'key' => 'ip-hour:203.0.113.10'],
        ]);
    });

    it('keeps the minute and the hour counters in separate buckets', function () {
        [$perMinute, $perHour] = fortifyLimitsFor('register', throttledPost('/register'));

        expect($perMinute->key)->not->toBe($perHour->key);
    });

    it('ignores whatever address the sign up names', function () {
        $ada = fortifyLimitsFor('register', throttledPost('/register', ['email' => 'ada@example.com']));
        $grace = fortifyLimitsFor('register', throttledPost('/register', ['email' => 'grace@example.com']));

        expect(array_map(limitShape(...), $ada))->toBe(array_map(limitShape(...), $grace));
    });
});

describe('the password reset limiter', function () {
    it('allows five requests a minute and twenty an hour per IP when no address is given', function () {
        expect(array_map(limitShape(...), fortifyLimitsFor('password-reset', throttledPost('/forgot-password'))))->toBe([
            ['maxAttempts' => 5, 'decaySeconds' => 60, 'key' => 'ip-minute:203.0.113.10'],
            ['maxAttempts' => 20, 'decaySeconds' => 3600, 'key' => 'ip-hour:203.0.113.10'],
        ]);
    });

    it('adds three requests a minute per address and IP when an address is given', function () {
        $limits = fortifyLimitsFor('password-reset', throttledPost('/forgot-password', ['email' => 'ada@example.com']));

        expect($limits)->toHaveCount(3)
            ->and(limitShape($limits[2]))->toBe([
                'maxAttempts' => 3,
                'decaySeconds' => 60,
                'key' => 'email:ada@example.com|203.0.113.10',
            ]);
    });

    it('counts one address the same way however it is cased or padded', function (string $email) {
        $limits = fortifyLimitsFor('password-reset', throttledPost('/forgot-password', ['email' => $email]));

        expect($limits[2]->key)->toBe('email:ada@example.com|203.0.113.10');
    })->with([
        'mixed case' => 'Ada@Example.COM',
        'padded' => '  ada@example.com  ',
    ]);

    it('adds no address counter for an address it cannot read', function (mixed $email) {
        expect(fortifyLimitsFor('password-reset', throttledPost('/forgot-password', ['email' => $email])))->toHaveCount(2);
    })->with([
        'empty' => '',
        'whitespace only' => '   ',
        'an array' => [['ada@example.com']],
    ]);

    it('keeps its three counters in separate buckets', function () {
        $keys = array_map(
            fn (Limit $limit): mixed => $limit->key,
            fortifyLimitsFor('password-reset', throttledPost('/forgot-password', ['email' => 'ada@example.com'])),
        );

        expect(array_unique($keys))->toHaveCount(3);
    });
});

describe('the fortify routes that ship without a throttle', function () {
    it('throttles each one with its own limiter', function (string $route, string $throttle) {
        expect(fortifyRouteMiddleware($route))->toContain($throttle);
    })->with([
        'registration' => ['register.store', 'throttle:register'],
        'the reset link request' => ['password.email', 'throttle:password-reset'],
        'the new password submission' => ['password.update', 'throttle:password-reset'],
    ]);

    it('throttles each one exactly once', function (string $route) {
        $throttles = array_filter(
            fortifyRouteMiddleware($route),
            fn (string $middleware): bool => str_starts_with($middleware, 'throttle:'),
        );

        expect($throttles)->toHaveCount(1);
    })->with(['register.store', 'password.email', 'password.update']);

    it('leaves the sign in route on the sign in limiter', function () {
        expect(fortifyRouteMiddleware('login.store'))->toContain('throttle:login')
            ->not->toContain('throttle:register')
            ->not->toContain('throttle:password-reset');
    });
});
