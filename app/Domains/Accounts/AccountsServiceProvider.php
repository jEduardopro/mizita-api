<?php

declare(strict_types=1);

namespace App\Domains\Accounts;

use App\Domains\Accounts\Contracts\AccountRepository;
use App\Domains\Accounts\Contracts\GoogleIdentityVerifier;
use App\Domains\Accounts\Contracts\OwnedBusinesses;
use App\Domains\Accounts\Contracts\PasskeyDirectory;
use App\Domains\Accounts\Contracts\PasswordHasher;
use App\Domains\Accounts\Contracts\PasswordVerifier;
use App\Domains\Accounts\Contracts\SecondFactorVerifier;
use App\Domains\Accounts\Contracts\SocialIdentityRepository;
use App\Domains\Accounts\Contracts\TeamMemberships;
use App\Domains\Accounts\Contracts\TemporaryPasswordGenerator;
use App\Domains\Accounts\Contracts\TemporaryPasswordVault;
use App\Domains\Accounts\Contracts\UpcomingBookings;
use App\Domains\Accounts\Infrastructure\Auth\AccountsIncludingDeletedUserProvider;
use App\Domains\Accounts\Infrastructure\Auth\DatabaseAccountSessions;
use App\Domains\Accounts\Infrastructure\Auth\DiscardTemporaryPasswordOnLogin;
use App\Domains\Accounts\Infrastructure\Eloquent\EloquentAccountRepository;
use App\Domains\Accounts\Infrastructure\Eloquent\EloquentPasskeyDirectory;
use App\Domains\Accounts\Infrastructure\Eloquent\EloquentSocialIdentityRepository;
use App\Domains\Accounts\Infrastructure\Eloquent\Models\PasskeyModel;
use App\Domains\Accounts\Infrastructure\Gateways\AppointmentsUpcomingBookings;
use App\Domains\Accounts\Infrastructure\Gateways\BusinessesOwnedBusinesses;
use App\Domains\Accounts\Infrastructure\Gateways\StaffTeamMemberships;
use App\Domains\Accounts\Infrastructure\Google\SocialiteGoogleIdentityVerifier;
use App\Domains\Accounts\Infrastructure\Http\Controllers\SignInSecurityController;
use App\Domains\Accounts\Infrastructure\Passkeys\PasskeyRegisteredResponse;
use App\Domains\Accounts\Infrastructure\Passwords\EncryptedTemporaryPasswordVault;
use App\Domains\Accounts\Infrastructure\Passwords\FrameworkPasswordHasher;
use App\Domains\Accounts\Infrastructure\Passwords\FrameworkPasswordVerifier;
use App\Domains\Accounts\Infrastructure\Passwords\RandomTemporaryPasswordGenerator;
use App\Domains\Accounts\Infrastructure\TwoFactor\FortifySecondFactorVerifier;
use App\Shared\Contracts\AccountSessions;
use Illuminate\Auth\Events\Login;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Laravel\Passkeys\Contracts\PasskeyRegistrationResponse;
use Laravel\Passkeys\Passkeys;

final class AccountsServiceProvider extends ServiceProvider
{
    private const SIGN_IN_SECURITY_READS_PER_MINUTE = 60;

    public function register(): void
    {
        $this->app->bind(AccountRepository::class, EloquentAccountRepository::class);
        $this->app->bind(SocialIdentityRepository::class, EloquentSocialIdentityRepository::class);
        $this->app->bind(GoogleIdentityVerifier::class, SocialiteGoogleIdentityVerifier::class);
        $this->app->bind(TemporaryPasswordGenerator::class, RandomTemporaryPasswordGenerator::class);
        $this->app->bind(PasswordHasher::class, FrameworkPasswordHasher::class);
        $this->app->bind(TemporaryPasswordVault::class, EncryptedTemporaryPasswordVault::class);
        $this->app->bind(PasswordVerifier::class, FrameworkPasswordVerifier::class);
        $this->app->bind(AccountSessions::class, DatabaseAccountSessions::class);
        $this->app->bind(TeamMemberships::class, StaffTeamMemberships::class);
        $this->app->bind(UpcomingBookings::class, AppointmentsUpcomingBookings::class);
        $this->app->bind(OwnedBusinesses::class, BusinessesOwnedBusinesses::class);
        $this->app->bind(SecondFactorVerifier::class, FortifySecondFactorVerifier::class);
        $this->app->bind(PasskeyDirectory::class, EloquentPasskeyDirectory::class);
        $this->app->bind(PasskeyRegistrationResponse::class, PasskeyRegisteredResponse::class);
    }

    public function boot(): void
    {
        Event::listen(Login::class, DiscardTemporaryPasswordOnLogin::class);

        Passkeys::usePasskeyModel(PasskeyModel::class);

        $this->registerUserProviderIncludingDeletedAccounts();

        $this->registerSignInSecurityLimiter();

        Route::middleware('web')
            ->group(__DIR__.'/Infrastructure/Http/web.php');

        Route::prefix('api')
            ->middleware('api')
            ->group(__DIR__.'/Infrastructure/Http/routes.php');

        Route::prefix('api')
            ->middleware(['api', 'auth:sanctum', 'throttle:6,1'])
            ->group(__DIR__.'/Infrastructure/Http/me-account.php');

        Route::prefix('api')
            ->middleware(['api', 'throttle:10,1'])
            ->group(__DIR__.'/Infrastructure/Http/reactivation.php');
    }

    private function registerUserProviderIncludingDeletedAccounts(): void
    {
        Auth::provider(
            AccountsIncludingDeletedUserProvider::DRIVER,
            static fn (Application $app, array $config): AccountsIncludingDeletedUserProvider => new AccountsIncludingDeletedUserProvider(
                $app->make('hash'),
                $config['model'],
            ),
        );
    }

    private function registerSignInSecurityLimiter(): void
    {
        RateLimiter::for(
            SignInSecurityController::RATE_LIMITER,
            static fn (Request $request): Limit => Limit::perMinute(self::SIGN_IN_SECURITY_READS_PER_MINUTE)
                ->by('account:'.(string) $request->user()?->getAuthIdentifier()),
        );
    }
}
