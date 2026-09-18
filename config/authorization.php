<?php

declare(strict_types=1);

use App\Domains\Staff\Infrastructure\Permissions\SeededStaffRole;
use App\Shared\ValueObjects\AuthorizationScope;

return [

    'permissions' => [

        'manage_business' => [
            'scope' => AuthorizationScope::Business,
            'module' => 'business',
        ],

        'view_business_settings' => [
            'scope' => AuthorizationScope::Business,
            'module' => 'business',
        ],

        'edit_business_settings' => [
            'scope' => AuthorizationScope::Business,
            'module' => 'business',
        ],

        'manage_staff' => [
            'scope' => AuthorizationScope::Business,
            'module' => 'staff',
        ],

        'view_services' => [
            'scope' => AuthorizationScope::Business,
            'module' => 'services',
        ],

        'create_service' => [
            'scope' => AuthorizationScope::Business,
            'module' => 'services',
        ],

        'edit_service' => [
            'scope' => AuthorizationScope::Business,
            'module' => 'services',
        ],

        'delete_service' => [
            'scope' => AuthorizationScope::Business,
            'module' => 'services',
        ],

        'view_customers' => [
            'scope' => AuthorizationScope::Business,
            'module' => 'customers',
        ],

        'create_customer' => [
            'scope' => AuthorizationScope::Business,
            'module' => 'customers',
        ],

        'edit_customer' => [
            'scope' => AuthorizationScope::Business,
            'module' => 'customers',
        ],

        'delete_customer' => [
            'scope' => AuthorizationScope::Business,
            'module' => 'customers',
        ],

        'view_appointments' => [
            'scope' => AuthorizationScope::Business,
            'module' => 'appointments',
        ],

        'create_appointment' => [
            'scope' => AuthorizationScope::Business,
            'module' => 'appointments',
        ],

        'edit_appointment' => [
            'scope' => AuthorizationScope::Business,
            'module' => 'appointments',
        ],

        'delete_appointment' => [
            'scope' => AuthorizationScope::Business,
            'module' => 'appointments',
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
            'permissions' => ['view_services'],
        ],

    ],

];
