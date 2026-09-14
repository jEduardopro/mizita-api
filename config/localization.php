<?php

declare(strict_types=1);

return [

    'supported' => ['es', 'en'],

    'default' => env('APP_LOCALE', 'es'),

    'cookie' => 'locale',

    'cookie_lifetime' => 60 * 24 * 365,

];
