<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Payment Method Labels
    |--------------------------------------------------------------------------
    |
    | What each row of the payment_methods catalogue is called. The table stores
    | the code and nothing else, exactly as the permissions table stores a key,
    | so renaming a method is a translation edit rather than a migration.
    |
    | A code with no entry here surfaces as the key itself, so the seeder and
    | this file move together. "card" lands the day the integrations module can
    | actually charge one.
    |
    */

    'cash' => [
        'label' => 'En efectivo',
    ],

    'bank_transfer' => [
        'label' => 'Transferencia bancaria',
    ],

];
