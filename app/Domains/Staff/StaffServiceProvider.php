<?php

declare(strict_types=1);

namespace App\Domains\Staff;

use App\Domains\Staff\Contracts\StaffMemberRepository;
use App\Domains\Staff\Infrastructure\Eloquent\EloquentStaffMemberRepository;
use App\Domains\Staff\Infrastructure\Eloquent\Models\StaffMemberModel;
use App\Domains\Staff\Infrastructure\Gateways\EloquentBusinessMembership;
use App\Shared\Contracts\BusinessMembership;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\ServiceProvider;

final class StaffServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(StaffMemberRepository::class, EloquentStaffMemberRepository::class);
        $this->app->bind(BusinessMembership::class, EloquentBusinessMembership::class);
    }

    public function boot(): void
    {
        Relation::enforceMorphMap([
            'staff_member' => StaffMemberModel::class,
        ]);
    }
}
