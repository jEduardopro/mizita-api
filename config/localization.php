<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Supported Locales
    |--------------------------------------------------------------------------
    |
    | This file is the single source of truth for which locales exist in the
    | platform. Everything that negotiates a language reads it from here: the
    | SetLocale middleware, the Inertia shared props, and - through the
    | `supportedLocales` prop - the i18next instance the frontend boots with.
    | The frontend mirrors this list; it never invents one of its own.
    |
    | The order matters: it is the preference order used when an Accept-Language
    | header offers several acceptable languages, so the first entry wins ties.
    | Every locale listed here must have a matching directory under lang/.
    |
    */

    'supported' => ['es', 'en'],

    /*
    |--------------------------------------------------------------------------
    | Default Locale
    |--------------------------------------------------------------------------
    |
    | Used when no request carries a usable preference. It stays tied to
    | APP_LOCALE so the application default and the negotiated default can
    | never drift apart.
    |
    */

    'default' => env('APP_LOCALE', 'es'),

    /*
    |--------------------------------------------------------------------------
    | Locale Cookie
    |--------------------------------------------------------------------------
    |
    | An explicit choice (the ?lang= query parameter) is remembered in this
    | cookie so it survives subsequent requests. The name is part of the
    | contract with the frontend, which reads and writes the same cookie.
    |
    */

    'cookie' => 'locale',

    // One year, in minutes - the unit Laravel's cookie queue expects.
    'cookie_lifetime' => 60 * 24 * 365,

];
