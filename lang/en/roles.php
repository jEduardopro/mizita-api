<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Role Labels
    |--------------------------------------------------------------------------
    |
    | What each role in config/authorization.php is called. As with permissions,
    | the roles table stores the key and never the wording.
    |
    | One key serves every copy of a template role: each business owns its own
    | "staff" row, all of them carry the same name, and all of them therefore
    | resolve to roles.staff here. What a business changes is the permissions
    | attached to its own row, not what the role is called.
    |
    */

    'owner' => [
        'label' => 'Owner',
        'description' => 'Registered the business. Holds every permission, and the role itself cannot be edited or removed.',
    ],

    'staff' => [
        'label' => 'Staff',
        'description' => 'Works at the business and operates its agenda. Each business decides what this role is allowed to do.',
    ],

];
