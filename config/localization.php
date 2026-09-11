<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Supported Locales
    |--------------------------------------------------------------------------
    |
    | This file is the single source of truth for which locales exist in the
    | platform. Everything that resolves a language reads it from here: the
    | SetLocale middleware, the Inertia shared props, and - through the
    | `supportedLocales` prop - the i18next instance the frontend boots with.
    | The frontend mirrors this list; it never invents one of its own.
    |
    | The list is a guard, not a negotiation order: SetLocale checks a candidate
    | against it and nothing is ever inferred from Accept-Language, so no entry
    | wins over another - a request either names a supported locale explicitly or
    | gets `default`. The order is only what the language switcher renders.
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
    | APP_LOCALE so the application default and the resolved default can
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
    | That last word is the whole contract: this cookie has two writers, Laravel
    | on ?lang= and changeLocale() in the browser. So it is neither encrypted
    | (see encryptCookies() in bootstrap/app.php) nor HttpOnly (see SetLocale) -
    | it is a display preference, not a credential, and it has to stay readable
    | and writable from JavaScript. Making it HttpOnly does not fail loudly: the
    | browser just drops the frontend's write and the language silently reverts.
    |
    */

    'cookie' => 'locale',

    // One year, in minutes - the unit Laravel's cookie queue expects.
    'cookie_lifetime' => 60 * 24 * 365,

];
