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
     * Wire this domain's ports to their infrastructure adapters.
     *
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
        // The alias this domain answers to in polymorphic columns.
        // enforceMorphMap rather than morphMap, so an unmapped model raises
        // instead of quietly writing its FQCN into a short varchar that would
        // truncate it; it merges, so each domain keeps declaring its own alias
        // in its own provider.
        //
        // "account" used to be declared here too and has moved to
        // AppServiceProvider, where a platform-wide alias belongs: the model it
        // names is App\Models\User, which is nobody's domain.
        Relation::enforceMorphMap([
            'staff_member' => StaffMemberModel::class,
        ]);

        // No route group, deliberately: Staff exposes no endpoints today.
        // Onboarding reaches RegisterBusinessOwner through the port Businesses
        // declares, and inviting or managing a team is later work that brings
        // its own routes. There is nothing missing to restore here.
    }
}
