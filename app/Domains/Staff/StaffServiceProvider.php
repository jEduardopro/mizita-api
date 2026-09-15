<?php

declare(strict_types=1);

namespace App\Domains\Staff;

use App\Domains\Staff\Contracts\AccountDirectory;
use App\Domains\Staff\Contracts\StaffMemberRepository;
use App\Domains\Staff\Infrastructure\Eloquent\EloquentStaffMemberRepository;
use App\Domains\Staff\Infrastructure\Eloquent\Models\StaffMemberModel;
use App\Domains\Staff\Infrastructure\Gateways\AccountsAccountDirectory;
use App\Domains\Staff\Infrastructure\Gateways\EloquentBusinessMembership;
use App\Shared\Contracts\BusinessMembership;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

final class StaffServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(StaffMemberRepository::class, EloquentStaffMemberRepository::class);
        $this->app->bind(BusinessMembership::class, EloquentBusinessMembership::class);
        $this->app->bind(AccountDirectory::class, AccountsAccountDirectory::class);
    }

    public function boot(): void
    {
        Relation::enforceMorphMap([
            'staff_member' => StaffMemberModel::class,
        ]);

        Route::prefix('api')
            ->middleware(['api', 'auth:sanctum', 'business'])
            ->group(__DIR__.'/Infrastructure/Http/routes.php');
    }
}
