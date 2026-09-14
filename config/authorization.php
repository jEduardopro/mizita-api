<?php

declare(strict_types=1);

use App\Domains\Staff\Infrastructure\Permissions\SeededStaffRole;
use App\Shared\ValueObjects\AuthorizationScope;

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
            'id' => SeededStaffRole::OWNER_ID,
            'permissions' => '*',
        ],

        'staff' => [
            'scope' => AuthorizationScope::Business,
            'template' => true,
            'permissions' => [],
        ],

    ],

];
