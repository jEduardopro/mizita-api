<?php

declare(strict_types=1);

use App\Domains\Accounts\Infrastructure\Eloquent\Models\PasskeyModel;
use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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

function fortifyLimitFor(string $limiter, Request $request): Limit
{
    return RateLimiter::limiter($limiter)($request);
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

        expect(fortifyLimitFor('two-factor', $request)->maxAttempts)->toBe(5);
    });

    it('counts each pending sign in on its own', function () {
        $first = inertiaVisit('/two-factor-challenge');
        $first->session()->put('login.id', 7);
        $firstKey = fortifyLimitFor('two-factor', $first)->key;

        $second = inertiaVisit('/two-factor-challenge');
        $second->session()->put('login.id', 8);

        expect($firstKey)->not->toBe(fortifyLimitFor('two-factor', $second)->key);
    });
});

describe('the passkey limiter', function () {
    it('allows ten attempts a minute', function () {
        expect(fortifyLimitFor('passkeys', inertiaVisit('/passkeys/login/options'))->maxAttempts)->toBe(10);
    });

    it('counts each credential on its own', function () {
        $first = Request::create('/passkeys/login', 'POST', ['credential' => ['id' => 'credential-a']]);
        $second = Request::create('/passkeys/login', 'POST', ['credential' => ['id' => 'credential-b']]);

        expect(fortifyLimitFor('passkeys', $first)->key)->not->toBe(fortifyLimitFor('passkeys', $second)->key);
    });
});
