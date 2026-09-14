<?php

declare(strict_types=1);

use App\Domains\Staff\Infrastructure\Permissions\SeededStaffRole;
use App\Shared\ValueObjects\AuthorizationScope;

/*
| THIS FILE IS THE TEMPLATE. The roles table is not.
|
| Spatie's Role::findByParam() matches "business_id is null or business_id =
| <team>" and returns first() with no ORDER BY, so a global row and a
| per-business clone sharing a name are ambiguous: syncRoles('staff') would
| attach an undefined one of the two, and picking the global one would leave
| every permission the owner edited silently inert.
|
| **Never create a role row with a null business_id whose name is also a
| template name.** It is valid SQL, nothing fails, and role assignment quietly
| starts attaching the wrong row. The partial unique indexes added with the scope
| column stop a *duplicate* global row; they cannot stop this one.
|
| Entry shape:
|   scope        AuthorizationScope - which plane may hold it.
|   module       display grouping for a future roles screen, deliberately not
|                persisted: regrouping checkboxes must never be a migration.
|   template     true  -> never a global row; cloned into every business.
|                false -> exactly one global row, never cloned, never editable.
|   permissions  a list of names, or '*' for every permission in the role's own
|                scope. '*' never crosses a scope, which is where "a business
|                owner must never hold a platform permission" lives.
|   id           optional. Pins the primary key.
|
| owner is template => false and must stay so. That is a security invariant:
| StaffRoleAssignments::ownsAnyBusiness() matches roles.name = 'owner' across
| every team, and model_has_roles_single_owner_unique names its primary key
| literally. Clone owner and an account can quietly come to own two businesses.
*/

return [

    'permissions' => [

        'business.manage' => [
            'scope' => AuthorizationScope::Business,
            'module' => 'business',
        ],

        'staff.manage' => [
            'scope' => AuthorizationScope::Business,
            'module' => 'staff',
        ],

    ],

    'roles' => [

        'owner' => [
            'scope' => AuthorizationScope::Business,
            'template' => false,
            // The one role whose primary key is written into a database
            // guarantee, so it is pinned rather than left to the sequence.
            'id' => SeededStaffRole::OWNER_ID,
            'permissions' => '*',
        ],

        'staff' => [
            'scope' => AuthorizationScope::Business,
            'template' => true,
            // Nothing by default. What a business's staff may do is the
            // business's own decision, made on its clone; re-seeding must never
            // restore a permission an owner revoked.
            'permissions' => [],
        ],

    ],

];
