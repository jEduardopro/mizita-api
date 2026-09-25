<?php

declare(strict_types=1);

namespace App\Domains\Accounts;

use App\Domains\Accounts\Contracts\AccountRepository;
use App\Domains\Accounts\Contracts\GoogleIdentityVerifier;
use App\Domains\Accounts\Contracts\PasswordHasher;
use App\Domains\Accounts\Contracts\SocialIdentityRepository;
use App\Domains\Accounts\Contracts\TemporaryPasswordGenerator;
use App\Domains\Accounts\Contracts\TemporaryPasswordVault;
use App\Domains\Accounts\Infrastructure\Auth\DiscardTemporaryPasswordOnLogin;
use App\Domains\Accounts\Infrastructure\Eloquent\EloquentAccountRepository;
use App\Domains\Accounts\Infrastructure\Eloquent\EloquentSocialIdentityRepository;
use App\Domains\Accounts\Infrastructure\Google\SocialiteGoogleIdentityVerifier;
use App\Domains\Accounts\Infrastructure\Passwords\EncryptedTemporaryPasswordVault;
use App\Domains\Accounts\Infrastructure\Passwords\FrameworkPasswordHasher;
use App\Domains\Accounts\Infrastructure\Passwords\RandomTemporaryPasswordGenerator;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

final class AccountsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(AccountRepository::class, EloquentAccountRepository::class);
        $this->app->bind(SocialIdentityRepository::class, EloquentSocialIdentityRepository::class);
        $this->app->bind(GoogleIdentityVerifier::class, SocialiteGoogleIdentityVerifier::class);
        $this->app->bind(TemporaryPasswordGenerator::class, RandomTemporaryPasswordGenerator::class);
        $this->app->bind(PasswordHasher::class, FrameworkPasswordHasher::class);
        $this->app->bind(TemporaryPasswordVault::class, EncryptedTemporaryPasswordVault::class);
    }

    public function boot(): void
    {
        Event::listen(Login::class, DiscardTemporaryPasswordOnLogin::class);

        Route::middleware('web')
            ->group(__DIR__.'/Infrastructure/Http/web.php');

        Route::prefix('api')
            ->middleware(['api', 'throttle:6,1'])
            ->group(__DIR__.'/Infrastructure/Http/routes.php');
    }
}
