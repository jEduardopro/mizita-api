<?php

declare(strict_types=1);

return [

    /*
    | The single source of truth for which locales exist. A guard, not a
    | negotiation order: SetLocale checks a candidate against it and nothing is
    | inferred from Accept-Language, so a request either names a supported locale
    | explicitly or gets `default`. Every entry needs a directory under lang/.
    */

    'supported' => ['es', 'en'],

    /* Tied to APP_LOCALE so the application default and the resolved default cannot drift. */

    'default' => env('APP_LOCALE', 'es'),

    /*
    | This cookie has two writers, Laravel on ?lang= and changeLocale() in the
    | browser, so it is neither encrypted (see encryptCookies() in
    | bootstrap/app.php) nor HttpOnly (see SetLocale). Making it HttpOnly does
    | not fail loudly: the browser drops the frontend's write and the language
    | silently reverts.
    */

    'cookie' => 'locale',

    // One year, in minutes - the unit Laravel's cookie queue expects.
    'cookie_lifetime' => 60 * 24 * 365,

];
