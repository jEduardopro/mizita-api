<?php

namespace App\Providers;

use App\Actions\Fortify\CreateNewUser;
use App\Actions\Fortify\ResetUserPassword;
use App\Actions\Fortify\UpdateUserPassword;
use App\Actions\Fortify\UpdateUserProfileInformation;
use App\Domains\Accounts\Infrastructure\Auth\PasswordCredentialsAuthenticator;
use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Laravel\Fortify\Actions\RedirectIfTwoFactorAuthenticatable;
use Laravel\Fortify\Fortify;
use Laravel\Passkeys\Contracts\PasskeyUser;
use Laravel\Passkeys\Passkeys;

class FortifyServiceProvider extends ServiceProvider
{
    private const REGISTER_LIMITER = 'register';

    private const PASSWORD_RESET_LIMITER = 'password-reset';

    private const UNTHROTTLED_FORTIFY_ROUTES = [
        'register.store' => self::REGISTER_LIMITER,
        'password.email' => self::PASSWORD_RESET_LIMITER,
        'password.update' => self::PASSWORD_RESET_LIMITER,
    ];

    private const LOGIN_ATTEMPTS_PER_EMAIL_PER_MINUTE = 5;

    private const LOGIN_ATTEMPTS_PER_IP_PER_MINUTE = 20;

    private const PASSKEY_ATTEMPTS_PER_CREDENTIAL_PER_MINUTE = 10;

    private const PASSKEY_ATTEMPTS_PER_IP_PER_MINUTE = 20;

    private const REGISTRATIONS_PER_IP_PER_MINUTE = 5;

    private const REGISTRATIONS_PER_IP_PER_HOUR = 20;

    private const PASSWORD_RESETS_PER_IP_PER_MINUTE = 5;

    private const PASSWORD_RESETS_PER_IP_PER_HOUR = 20;

    private const PASSWORD_RESETS_PER_EMAIL_PER_MINUTE = 3;

    public function boot(): void
    {
        Fortify::createUsersUsing(CreateNewUser::class);
        Fortify::updateUserProfileInformationUsing(UpdateUserProfileInformation::class);
        Fortify::updateUserPasswordsUsing(UpdateUserPassword::class);
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);
        Fortify::redirectUserForTwoFactorAuthenticationUsing(RedirectIfTwoFactorAuthenticatable::class);
        Fortify::authenticateUsing(
            fn (Request $request): ?User => $this->app->make(PasswordCredentialsAuthenticator::class)->authenticate($request),
        );
        Passkeys::authorizeLoginUsing(
            fn (Request $request, ?PasskeyUser $user): bool => $this->isActiveAccount($user),
        );

        $this->registerViews();

        $this->registerRateLimiters();

        $this->app->booted(fn () => $this->throttleUnthrottledFortifyRoutes($this->app->make(Router::class)));
    }

    private function registerViews(): void
    {
        Fortify::loginView(fn () => Inertia::render('auth/login'));

        Fortify::registerView(fn () => Inertia::render('auth/register'));

        Fortify::requestPasswordResetLinkView(fn () => Inertia::render('auth/forgot-password'));

        Fortify::resetPasswordView(fn (Request $request) => Inertia::render('auth/reset-password', [
            'token' => (string) $request->route('token'),
            'email' => (string) $request->query('email', ''),
        ]));

        Fortify::twoFactorChallengeView(fn () => Inertia::render('auth/two-factor-challenge'));

        Fortify::confirmPasswordView(fn () => Inertia::render('auth/confirm-password'));
    }

    private function registerRateLimiters(): void
    {
        RateLimiter::for('login', function (Request $request): array {
            $throttleKey = Str::transliterate(Str::lower($request->input(Fortify::username())).'|'.$request->ip());

            return [
                Limit::perMinute(self::LOGIN_ATTEMPTS_PER_EMAIL_PER_MINUTE)->by($throttleKey),
                Limit::perMinute(self::LOGIN_ATTEMPTS_PER_IP_PER_MINUTE)->by('ip:'.$request->ip()),
            ];
        });

        RateLimiter::for('two-factor', function (Request $request) {
            return Limit::perMinute(5)->by($request->session()->get('login.id'));
        });

        RateLimiter::for('passkeys', function (Request $request): array {
            $credentialId = $request->input('credential.id');

            return [
                Limit::perMinute(self::PASSKEY_ATTEMPTS_PER_CREDENTIAL_PER_MINUTE)->by(
                    ($credentialId ?: $request->session()->getId()).'|'.$request->ip()
                ),
                Limit::perMinute(self::PASSKEY_ATTEMPTS_PER_IP_PER_MINUTE)->by('ip:'.$request->ip()),
            ];
        });

        RateLimiter::for(self::REGISTER_LIMITER, fn (Request $request): array => [
            Limit::perMinute(self::REGISTRATIONS_PER_IP_PER_MINUTE)->by('ip-minute:'.$request->ip()),
            Limit::perHour(self::REGISTRATIONS_PER_IP_PER_HOUR)->by('ip-hour:'.$request->ip()),
        ]);

        RateLimiter::for(self::PASSWORD_RESET_LIMITER, fn (Request $request): array => [
            Limit::perMinute(self::PASSWORD_RESETS_PER_IP_PER_MINUTE)->by('ip-minute:'.$request->ip()),
            Limit::perHour(self::PASSWORD_RESETS_PER_IP_PER_HOUR)->by('ip-hour:'.$request->ip()),
            ...$this->passwordResetLimitsPerEmail($request),
        ]);
    }

    /**
     * @return list<Limit>
     */
    private function passwordResetLimitsPerEmail(Request $request): array
    {
        $email = $request->input(Fortify::email());

        if (! is_string($email) || trim($email) === '') {
            return [];
        }

        $throttleKey = Str::transliterate(Str::lower(trim($email)).'|'.$request->ip());

        return [Limit::perMinute(self::PASSWORD_RESETS_PER_EMAIL_PER_MINUTE)->by('email:'.$throttleKey)];
    }

    private function throttleUnthrottledFortifyRoutes(Router $router): void
    {
        foreach ($router->getRoutes()->getRoutes() as $route) {
            $limiter = self::UNTHROTTLED_FORTIFY_ROUTES[(string) $route->getName()] ?? null;

            if ($limiter === null) {
                continue;
            }

            $route->middleware('throttle:'.$limiter);
        }
    }

    private function isActiveAccount(?PasskeyUser $user): bool
    {
        return $user instanceof User && ! $user->trashed();
    }
}
