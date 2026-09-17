<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Permission Labels
    |--------------------------------------------------------------------------
    |
    | What each permission in config/authorization.php is called, and what it
    | actually lets someone do. Labels never live in the database: the
    | permissions table stores the key, and the key is resolved here, so a
    | wording change is a translation edit rather than a migration.
    |
    | One flat entry per permission name, keyed exactly as the name is written
    | in the catalogue - "manage_business" is stored with the description key
    | "permissions.manage_business.description".
    |
    | Nothing enforces that a key stored in the database exists here. A rename on
    | one side and not the other shows the caller the key itself, so the two move
    | together or not at all.
    |
    */

    'manage_business' => [
        'label' => 'Manage the business',
        'description' => 'Edit the business profile, its contact details, its time zone and its booking policy.',
    ],

    'view_business_settings' => [
        'label' => 'View the business settings',
        'description' => 'See the brand, contact details, location, opening hours and links of the business.',
    ],

    'edit_business_settings' => [
        'label' => 'Edit the business settings',
        'description' => 'Change the brand, contact details, location, opening hours and links of the business.',
    ],

    'manage_staff' => [
        'label' => 'Manage staff',
        'description' => 'Invite people to the business, change what they are allowed to do, and remove them.',
    ],

    'view_services' => [
        'label' => 'View services',
        'description' => 'See the services the business offers, their prices and who can perform them.',
    ],

    'create_service' => [
        'label' => 'Create services',
        'description' => 'Add a new service to the catalogue, or duplicate an existing one.',
    ],

    'edit_service' => [
        'label' => 'Edit services',
        'description' => 'Change a service, its image and the people who can perform it.',
    ],

    'delete_service' => [
        'label' => 'Delete services',
        'description' => 'Remove a service from the catalogue.',
    ],

    'view_customers' => [
        'label' => 'View customers',
        'description' => 'See the customers of the business, their contact details, their address and their notes.',
    ],

    'create_customer' => [
        'label' => 'Create customers',
        'description' => 'Add a new customer to the business.',
    ],

    'edit_customer' => [
        'label' => 'Edit customers',
        'description' => 'Change a customer, their contact details, their address and their notes.',
    ],

    'delete_customer' => [
        'label' => 'Delete customers',
        'description' => 'Remove a customer from the business, together with their phone and address.',
    ],

];
