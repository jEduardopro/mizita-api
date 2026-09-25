<?php

declare(strict_types=1);

use App\Domains\Accounts\Contracts\PasskeyDirectory;
use App\Domains\Accounts\Contracts\SecondFactorVerifier;
use App\Domains\Accounts\Infrastructure\Eloquent\EloquentPasskeyDirectory;
use App\Domains\Accounts\Infrastructure\Eloquent\Models\PasskeyModel;
use App\Domains\Accounts\Infrastructure\Http\Controllers\SignInSecurityController;
use App\Domains\Accounts\Infrastructure\Passkeys\PasskeyRegisteredResponse;
use App\Domains\Accounts\Infrastructure\TwoFactor\FortifySecondFactorVerifier;
use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Laravel\Passkeys\Contracts\PasskeyRegistrationResponse;
use Laravel\Passkeys\Http\Responses\PasskeyRegistrationResponse as VendorPasskeyRegistrationResponse;
use Laravel\Passkeys\Passkeys;
use Tests\TestCase;

uses(TestCase::class);

function signInSecurityLimitFor(?User $caller): Limit
{
    $request = Request::create('/api/me/security');
    $request->setUserResolver(fn () => $caller);

    return RateLimiter::limiter(SignInSecurityController::RATE_LIMITER)($request);
}

describe('the second factor and passkey ports', function () {
    it('binds each port to its adapter', function (string $port, string $adapter) {
        expect($this->app->make($port))->toBeInstanceOf($adapter);
    })->with([
        'second factor verifier' => [SecondFactorVerifier::class, FortifySecondFactorVerifier::class],
        'passkey directory' => [PasskeyDirectory::class, EloquentPasskeyDirectory::class],
    ]);
});

describe('the passkey registration response', function () {
    it('answers with ours rather than the vendor default', function () {
        expect($this->app->make(PasskeyRegistrationResponse::class))
            ->toBeInstanceOf(PasskeyRegisteredResponse::class)
            ->not->toBeInstanceOf(VendorPasskeyRegistrationResponse::class);
    });

    it('hands out a fresh response per registration, so one passkey never answers for another', function () {
        expect($this->app->make(PasskeyRegistrationResponse::class))
            ->not->toBe($this->app->make(PasskeyRegistrationResponse::class));
    });
});

describe('the passkey model the vendor package uses', function () {
    it('is ours', function () {
        expect(Passkeys::passkeyModel())->toBe(PasskeyModel::class);
    });

    it('turns a passkey key that is not a uuid into a not found, never a server error', function () {
        $binder = Route::getBindingCallback('passkey');

        expect(fn () => $binder('42', null))->toThrow(ModelNotFoundException::class);
    });
});

describe('the sign in security limiter', function () {
    it('allows sixty reads a minute', function () {
        $limit = signInSecurityLimitFor((new User)->forceFill(['id' => 7]));

        expect($limit->maxAttempts)->toBe(60)
            ->and($limit->decaySeconds)->toBe(60);
    });

    it('counts each account on its own', function () {
        expect(signInSecurityLimitFor((new User)->forceFill(['id' => 7]))->key)
            ->not->toBe(signInSecurityLimitFor((new User)->forceFill(['id' => 8]))->key);
    });
});
