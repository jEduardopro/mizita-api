<?php

declare(strict_types=1);

namespace App\Domains\Accounts\Infrastructure\Http\Controllers;

use App\Domains\Accounts\Application\Dtos\AuthenticateWithGoogleInput;
use App\Domains\Accounts\Application\UseCases\AuthenticateWithGoogle;
use App\Domains\Accounts\Exceptions\GoogleEmailNotVerified;
use App\Domains\Accounts\Infrastructure\Auth\AccountAuthenticator;
use App\Domains\Accounts\Infrastructure\Google\SocialiteGoogleIdentity;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Laravel\Socialite\AbstractUser;
use Laravel\Socialite\Facades\Socialite;
use Throwable;

final class GoogleCallbackController extends Controller
{
    private const DRIVER = 'google';

    public function __invoke(
        Request $request,
        SocialiteGoogleIdentity $identities,
        AuthenticateWithGoogle $authenticate,
        AccountAuthenticator $authenticator,
    ): RedirectResponse {
        try {
            /** @var AbstractUser $googleUser */
            $googleUser = Socialite::driver(self::DRIVER)->user();

            $identity = $identities->toGoogleIdentity($googleUser);
        } catch (Throwable) {
            // Denied consent, a stale "state" value or a profile we cannot read.
            // The person lands back on the form, never on a 500.
            return $this->backToLogin('messages.errors.google_sign_in_failed');
        }

        try {
            $account = $authenticate->handle(AuthenticateWithGoogleInput::fromGoogleIdentity($identity));
        } catch (GoogleEmailNotVerified) {
            return $this->backToLogin('messages.errors.google_email_not_verified');
        }

        $authenticator->startSessionFor($account->id);

        // Mandatory, not defensive: a fresh session id is what closes session
        // fixation, where an attacker plants a known id before the sign in.
        $request->session()->regenerate();

        return redirect()->intended((string) config('fortify.home'));
    }

    /** The errors bag is already a shared Inertia prop, so the login page renders this unwired. */
    private function backToLogin(string $messageKey): RedirectResponse
    {
        return redirect()->route('login')->withErrors(['google' => (string) __($messageKey)]);
    }
}
