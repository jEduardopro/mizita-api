<?php

declare(strict_types=1);

namespace App\Domains\Accounts\Infrastructure\Http\Controllers;

use App\Domains\Accounts\Application\Dtos\AuthenticateWithGoogleInput;
use App\Domains\Accounts\Application\UseCases\AuthenticateWithGoogle;
use App\Domains\Accounts\Exceptions\AccountPendingReactivation;
use App\Domains\Accounts\Infrastructure\Auth\AccountAuthenticator;
use App\Domains\Accounts\Infrastructure\Auth\PendingReactivation;
use App\Domains\Accounts\Infrastructure\Google\SocialiteGoogleIdentity;
use App\Http\Controllers\Controller;
use App\Http\Responses\WebResponder;
use App\Shared\Application\UseCaseError;
use App\Shared\Contracts\Clock;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Laravel\Socialite\AbstractUser;
use Laravel\Socialite\Facades\Socialite;
use Throwable;

final class GoogleCallbackController extends Controller
{
    private const DRIVER = 'google';

    private const LOGIN_ROUTE = 'login';

    private const ERROR_KEY = 'google';

    private const HANDSHAKE_FAILED_CODE = 'google_sign_in_failed';

    private const TWO_FACTOR_CHALLENGE_ROUTE = 'two-factor.login';

    public function __invoke(
        Request $request,
        SocialiteGoogleIdentity $identities,
        AuthenticateWithGoogle $authenticate,
        AccountAuthenticator $authenticator,
        PendingReactivation $pendingReactivation,
        Clock $clock,
        WebResponder $responder,
    ): RedirectResponse {
        try {
            /** @var AbstractUser $googleUser */
            $googleUser = Socialite::driver(self::DRIVER)->user();

            $identity = $identities->toGoogleIdentity($googleUser);
        } catch (Throwable) {
            return $responder->backToWithErrorCode(self::LOGIN_ROUTE, self::ERROR_KEY, self::HANDSHAKE_FAILED_CODE);
        }

        try {
            $response = $authenticate->handle(AuthenticateWithGoogleInput::fromGoogleIdentity($identity));

            if ($response->failed()) {
                return $this->redirectAfterFailure($response->error(), $pendingReactivation, $clock, $responder);
            }

            $account = $response->value();

            if ($account->requiresSecondFactor) {
                $authenticator->challengeSecondFactorFor($account->id);

                return redirect()->route(self::TWO_FACTOR_CHALLENGE_ROUTE);
            }

            $authenticator->startSessionFor($account->id);

            $request->session()->regenerate();

            return redirect()->intended((string) config('fortify.home'));
        } catch (Throwable $unexpected) {
            return $responder->unexpected($request, $unexpected, self::LOGIN_ROUTE, self::ERROR_KEY);
        }
    }

    private function redirectAfterFailure(
        UseCaseError $error,
        PendingReactivation $pendingReactivation,
        Clock $clock,
        WebResponder $responder,
    ): RedirectResponse {
        $cause = $error->cause();

        if (! $cause instanceof AccountPendingReactivation) {
            return $responder->backTo(self::LOGIN_ROUTE, self::ERROR_KEY, $error);
        }

        $pendingReactivation->remember($cause->accountId, $clock->now());

        return redirect()->route(AccountReactivationController::REACTIVATE_ROUTE);
    }
}
