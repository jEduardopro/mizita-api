<?php

declare(strict_types=1);

return [

    'greeting' => 'Hello!',

    'action' => 'Sign in',

    'temporary_password' => [
        'subject' => 'You have been invited to join :business on Mizita',
        'intro' => ':business has added you to their team on Mizita. Use the details below to sign in for the first time.',
        'email' => 'Email: :email',
        'password' => 'Temporary password: `:password`',
        'change_notice' => 'You will be asked to choose your own password as soon as you sign in. If you were not expecting this invitation, you can ignore this email.',
    ],

    'added_to_team' => [
        'subject' => 'You have been added to :business on Mizita',
        'intro' => ':business has added you to their team on Mizita.',
        'sign_in_notice' => 'Sign in with your existing Mizita account to start working with them.',
    ],

];
