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
    | The nesting mirrors the permission name, which is what makes the value of
    | permissions.description resolve as written - "business.manage" is stored
    | with the key "permissions.business.manage.description".
    |
    | Nothing enforces that a key stored in the database exists here. A rename on
    | one side and not the other shows the caller the key itself, so the two move
    | together or not at all.
    |
    */

    'business' => [

        'manage' => [
            'label' => 'Manage the business',
            'description' => 'Edit the business profile, its contact details, its time zone and its booking policy.',
        ],

    ],

    'staff' => [

        'manage' => [
            'label' => 'Manage staff',
            'description' => 'Invite people to the business, change what they are allowed to do, and remove them.',
        ],

    ],

];
