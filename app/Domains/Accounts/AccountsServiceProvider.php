<?php

declare(strict_types=1);

namespace App\Domains\Accounts;

use App\Domains\Accounts\Contracts\AccountRepository;
use App\Domains\Accounts\Contracts\GoogleIdentityVerifier;
use App\Domains\Accounts\Contracts\SocialIdentityRepository;
use App\Domains\Accounts\Infrastructure\Eloquent\EloquentAccountRepository;
use App\Domains\Accounts\Infrastructure\Eloquent\EloquentSocialIdentityRepository;
use App\Domains\Accounts\Infrastructure\Google\SocialiteGoogleIdentityVerifier;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

final class AccountsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(AccountRepository::class, EloquentAccountRepository::class);
        $this->app->bind(SocialIdentityRepository::class, EloquentSocialIdentityRepository::class);
        $this->app->bind(GoogleIdentityVerifier::class, SocialiteGoogleIdentityVerifier::class);
    }

    /**
     * Two route groups, deliberately. The OAuth redirect and callback are browser
     * navigations: Socialite stores a "state" value in the session on the way out
     * and compares it on the way back, which only works on the "web" stack.
     *
     * The ID token endpoint is a real API call, unauthenticated by design, so it
     * carries its own rate limit. Accounts is a root domain, so neither group
     * binds a business context.
     */
    public function boot(): void
    {
        Route::middleware('web')
            ->group(__DIR__.'/Infrastructure/Http/web.php');

        Route::prefix('api')
            ->middleware(['api', 'throttle:6,1'])
            ->group(__DIR__.'/Infrastructure/Http/routes.php');
    }
}
