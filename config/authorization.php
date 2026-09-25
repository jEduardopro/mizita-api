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

        'view_staff_members' => [
            'scope' => AuthorizationScope::Business,
            'module' => 'staff',
        ],

        'create_staff_member' => [
            'scope' => AuthorizationScope::Business,
            'module' => 'staff',
        ],

        'edit_staff_member' => [
            'scope' => AuthorizationScope::Business,
            'module' => 'staff',
        ],

        'delete_staff_member' => [
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

        'manage_all_calendars' => [
            'scope' => AuthorizationScope::Business,
            'module' => 'appointments',
        ],

        'view_payments' => [
            'scope' => AuthorizationScope::Business,
            'module' => 'payments',
        ],

        'create_payment' => [
            'scope' => AuthorizationScope::Business,
            'module' => 'payments',
        ],

        'void_payment_transaction' => [
            'scope' => AuthorizationScope::Business,
            'module' => 'payments',
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
            'permissions' => [
                'view_services',
                'view_appointments',
                'create_appointment',
                'edit_appointment',
                'delete_appointment',
                'view_customers',
                'create_customer',
                'edit_customer',
                'view_payments',
                'create_payment',
            ],
        ],

        'no_access' => [
            'scope' => AuthorizationScope::Business,
            'template' => true,
            'permissions' => [],
        ],

    ],

];
