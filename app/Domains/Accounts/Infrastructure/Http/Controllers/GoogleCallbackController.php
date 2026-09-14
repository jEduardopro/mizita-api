<?php

declare(strict_types=1);

namespace App\Domains\Accounts\Infrastructure\Http\Controllers;

use App\Domains\Accounts\Application\Dtos\AuthenticateWithGoogleInput;
use App\Domains\Accounts\Application\UseCases\AuthenticateWithGoogle;
use App\Domains\Accounts\Infrastructure\Auth\AccountAuthenticator;
use App\Domains\Accounts\Infrastructure\Google\SocialiteGoogleIdentity;
use App\Http\Controllers\Controller;
use App\Http\Responses\WebResponder;
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

    public function __invoke(
        Request $request,
        SocialiteGoogleIdentity $identities,
        AuthenticateWithGoogle $authenticate,
        AccountAuthenticator $authenticator,
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
                return $responder->backTo(self::LOGIN_ROUTE, self::ERROR_KEY, $response->error());
            }

            $authenticator->startSessionFor($response->value()->id);

            $request->session()->regenerate();

            return redirect()->intended((string) config('fortify.home'));
        } catch (Throwable $unexpected) {
            return $responder->unexpected($request, $unexpected, self::LOGIN_ROUTE, self::ERROR_KEY);
        }
    }
}
