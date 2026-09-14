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

    public function boot(): void
    {
        Route::middleware('web')
            ->group(__DIR__.'/Infrastructure/Http/web.php');

        Route::prefix('api')
            ->middleware(['api', 'throttle:6,1'])
            ->group(__DIR__.'/Infrastructure/Http/routes.php');
    }
}
