<?php

declare(strict_types=1);

namespace App\Domains\Platform;

use App\Domains\Platform\Contracts\AuthenticatorApp;
use App\Domains\Platform\Contracts\BusinessOwnerAccounts;
use App\Domains\Platform\Contracts\BusinessPlans;
use App\Domains\Platform\Contracts\ImpersonationSession;
use App\Domains\Platform\Contracts\PasswordHasher;
use App\Domains\Platform\Contracts\PlatformAdminRepository;
use App\Domains\Platform\Contracts\PlatformBusinessDirectory;
use App\Domains\Platform\Infrastructure\Auth\GuardSignedInPlatformAdmin;
use App\Domains\Platform\Infrastructure\Auth\PendingPlatformLogin;
use App\Domains\Platform\Infrastructure\Auth\PlatformGuard;
use App\Domains\Platform\Infrastructure\Console\CreatePlatformAdminCommand;
use App\Domains\Platform\Infrastructure\Eloquent\EloquentPlatformAdminRepository;
use App\Domains\Platform\Infrastructure\Gateways\EloquentPlatformBusinessDirectory;
use App\Domains\Platform\Infrastructure\Gateways\StaffBusinessOwnerAccounts;
use App\Domains\Platform\Infrastructure\Gateways\SubscriptionsBusinessPlans;
use App\Domains\Platform\Infrastructure\Http\PlatformRoutes;
use App\Domains\Platform\Infrastructure\Impersonation\SessionImpersonationSession;
use App\Domains\Platform\Infrastructure\Passwords\FrameworkPasswordHasher;
use App\Domains\Platform\Infrastructure\TwoFactor\FortifyAuthenticatorApp;
use App\Shared\Contracts\ImpersonationStatus;
use App\Shared\Contracts\SignedInPlatformAdmin;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

final class PlatformServiceProvider extends ServiceProvider
{
    private const API_MIDDLEWARE = [
        'api',
        'auth:'.PlatformGuard::NAME,
        PlatformRoutes::SESSION_MIDDLEWARE,
        'throttle:60,1',
    ];

    private const LOGIN_ATTEMPTS_PER_MINUTE = 5;

    private const LOGIN_ATTEMPTS_PER_HOUR = 20;

    private const TWO_FACTOR_ATTEMPTS_PER_MINUTE = 5;

    private const EMAIL_FIELD = 'email';

    public function register(): void
    {
        $this->app->bind(PlatformBusinessDirectory::class, EloquentPlatformBusinessDirectory::class);
        $this->app->bind(BusinessPlans::class, SubscriptionsBusinessPlans::class);
        $this->app->bind(PlatformAdminRepository::class, EloquentPlatformAdminRepository::class);
        $this->app->bind(PasswordHasher::class, FrameworkPasswordHasher::class);
        $this->app->bind(AuthenticatorApp::class, FortifyAuthenticatorApp::class);
        $this->app->bind(BusinessOwnerAccounts::class, StaffBusinessOwnerAccounts::class);
        $this->app->bind(ImpersonationSession::class, SessionImpersonationSession::class);
        $this->app->bind(ImpersonationStatus::class, SessionImpersonationSession::class);
        $this->app->bind(SignedInPlatformAdmin::class, GuardSignedInPlatformAdmin::class);
    }

    public function boot(): void
    {
        $this->registerLoginLimiter();
        $this->registerTwoFactorLimiter();

        Route::middleware('web')
            ->group(__DIR__.'/Infrastructure/Http/web.php');

        Route::prefix('api')
            ->middleware(self::API_MIDDLEWARE)
            ->group(__DIR__.'/Infrastructure/Http/api.php');

        if ($this->app->runningInConsole()) {
            $this->commands([CreatePlatformAdminCommand::class]);
        }
    }

    private function registerLoginLimiter(): void
    {
        RateLimiter::for(PlatformRoutes::LOGIN_LIMITER, static fn (Request $request): array => [
            Limit::perMinute(self::LOGIN_ATTEMPTS_PER_MINUTE)
                ->by('platform-login-minute:'.self::attemptedEmailOf($request).'|'.(string) $request->ip()),
            Limit::perHour(self::LOGIN_ATTEMPTS_PER_HOUR)
                ->by('platform-login-hour:'.(string) $request->ip()),
        ]);
    }

    private function registerTwoFactorLimiter(): void
    {
        RateLimiter::for(PlatformRoutes::TWO_FACTOR_LIMITER, static fn (Request $request): Limit => Limit::perMinute(self::TWO_FACTOR_ATTEMPTS_PER_MINUTE)
            ->by('platform-two-factor:'.self::pendingLoginOf($request)));
    }

    private static function attemptedEmailOf(Request $request): string
    {
        $email = $request->input(self::EMAIL_FIELD);

        return is_string($email) ? mb_strtolower(trim($email)) : '';
    }

    private static function pendingLoginOf(Request $request): string
    {
        $pendingAdminId = $request->session()->get(PendingPlatformLogin::ADMIN_ID_SESSION_PATH);

        return is_string($pendingAdminId) ? $pendingAdminId : 'ip:'.(string) $request->ip();
    }
}
