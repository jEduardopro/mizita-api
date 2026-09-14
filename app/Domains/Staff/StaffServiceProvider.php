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
    /**
     * BusinessMembership is a shared port rather than one of this domain's:
     * a membership row is what makes an account a business user, so the rest
     * of the platform has to be able to ask about it without importing Staff.
     */
    public function register(): void
    {
        $this->app->bind(StaffMemberRepository::class, EloquentStaffMemberRepository::class);
        $this->app->bind(BusinessMembership::class, EloquentBusinessMembership::class);
    }

    public function boot(): void
    {
        // enforceMorphMap rather than morphMap, so an unmapped model raises
        // instead of quietly writing its FQCN into a short varchar that would
        // truncate it.
        Relation::enforceMorphMap([
            'staff_member' => StaffMemberModel::class,
        ]);

        // No route group, deliberately: Staff exposes no endpoints today.
        // Onboarding reaches RegisterBusinessOwner through the port Businesses
        // declares.
    }
}
