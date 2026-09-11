<?php

declare(strict_types=1);

use App\Http\Middleware\SetLocale;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Response;

/*
| SetLocale is prepended to both the web and the api middleware groups, so it is
| exercised through real requests against throwaway routes rather than by calling
| handle() directly: the wiring in bootstrap/app.php is as much part of the
| behaviour as the resolution chain itself.
|
| The chain has four steps, and Accept-Language is deliberately not one of them:
|
|     ?lang= -> X-Locale -> the locale cookie -> config('localization.default')
|
| See the "Accept-Language is not a source" block at the bottom for why, and for
| the assertions that would catch negotiation being reintroduced.
|
| No database is touched here.
*/

beforeEach(function () {
    // Echoes back what the middleware actually set. Asserting the response body
    // rather than the container afterwards proves the locale was in place *during*
    // the request, which is the only moment that matters.
    $probe = fn () => [
        'app' => App::getLocale(),
        'carbon' => Carbon::getLocale(),
    ];

    Route::middleware('web')->get('/_test/locale/web', $probe);
    Route::middleware('api')->get('/_test/locale/api', $probe);

    // Symfony's Request::create() injects a default "Accept-Language: en-us,en;q=0.5"
    // into every test request. The middleware no longer reads it, but the baseline
    // still blanks it so that no test can be read as depending on it either way;
    // the tests that assert it is ignored state the header themselves.
    $this->withHeader('Accept-Language', '');
});

afterEach(function () {
    // Carbon's locale is a static on the class, not state on the application, so
    // unlike App::setLocale() it survives into the next test. Resetting it keeps
    // this file from deciding how dates read anywhere else in the suite.
    Carbon::setLocale(config('localization.default'));
});

dataset('stacks', [
    'web stack' => ['/_test/locale/web'],
    'api stack' => ['/_test/locale/api'],
]);

describe('each source in isolation', function () {
    it('falls back to the configured default when the request states no preference', function (string $uri) {
        $this->get($uri)
            ->assertOk()
            ->assertExactJson(['app' => 'es', 'carbon' => 'es']);
    })->with('stacks');

    it('uses the lang query parameter', function (string $uri) {
        $this->get($uri.'?lang=en')
            ->assertExactJson(['app' => 'en', 'carbon' => 'en']);
    })->with('stacks');

    it('uses the X-Locale header', function (string $uri) {
        $this->get($uri, ['X-Locale' => 'en'])
            ->assertExactJson(['app' => 'en', 'carbon' => 'en']);
    })->with('stacks');

    it('uses the locale cookie', function (string $uri) {
        $this->withUnencryptedCookie('locale', 'en')
            ->get($uri)
            ->assertExactJson(['app' => 'en', 'carbon' => 'en']);
    })->with('stacks');

    it('falls back to the default when the request offers nothing', function () {
        // Driven directly rather than over HTTP, so this covers the middleware
        // itself rather than the group wiring. Request::create() synthesises
        // "Accept-Language: en-us,en;q=0.5" and it is left in place on purpose:
        // an untouched request from an English browser must still resolve to es.
        $request = Request::create('/');

        $locale = null;

        (new SetLocale)->handle($request, function () use (&$locale) {
            $locale = App::getLocale();

            return new Response;
        });

        expect($locale)->toBe('es');
    });
});

describe('priority', function () {
    it('prefers the lang query parameter over the X-Locale header', function () {
        $this->get('/_test/locale/web?lang=en', ['X-Locale' => 'es'])
            ->assertJsonPath('app', 'en');
    });

    it('prefers the X-Locale header over the locale cookie', function () {
        $this->withUnencryptedCookie('locale', 'es')
            ->get('/_test/locale/web', ['X-Locale' => 'en'])
            ->assertJsonPath('app', 'en');
    });

    it('prefers the locale cookie over the configured default', function () {
        $this->withUnencryptedCookie('locale', 'en')
            ->get('/_test/locale/web')
            ->assertJsonPath('app', 'en');
    });

    it('lets the lang query parameter outrank every lower source at once', function () {
        $this->withUnencryptedCookie('locale', 'es')
            ->get('/_test/locale/web?lang=en', [
                'X-Locale' => 'es',
                'Accept-Language' => 'es-ES,es;q=0.9',
            ])
            ->assertJsonPath('app', 'en');
    });
});

describe('unsupported candidates', function () {
    // The resolved locale becomes a path segment under lang/, so anything the
    // request offers has to be rejected rather than merely left unmatched.
    it('ignores an unsupported candidate and falls back to the default', function (array $query, array $headers, array $cookies) {
        // The default is deliberately moved off the head of the supported list, so
        // landing on "en" cannot be confused with silently picking supported[0].
        config(['localization.default' => 'en']);

        $test = $this;

        foreach ($cookies as $name => $value) {
            $test = $test->withUnencryptedCookie($name, $value);
        }

        $uri = '/_test/locale/web'.($query === [] ? '' : '?'.http_build_query($query));

        $test->get($uri, $headers)
            ->assertOk()
            ->assertExactJson(['app' => 'en', 'carbon' => 'en']);
    })->with([
        'unknown language in ?lang=' => [['lang' => 'fr'], [], []],
        'unknown language in X-Locale' => [[], ['X-Locale' => 'xx'], []],
        'unknown language in the cookie' => [[], [], ['locale' => 'de']],
        'path traversal in ?lang=' => [['lang' => '../../etc/passwd'], [], []],
        'path traversal in X-Locale' => [[], ['X-Locale' => '../../../lang/en'], []],
        'path traversal in the cookie' => [[], [], ['locale' => '../../etc/passwd']],
        'absolute path in ?lang=' => [['lang' => '/etc/passwd'], [], []],
        'null byte in ?lang=' => [['lang' => "es\0/../.."], [], []],
        'empty ?lang=' => [['lang' => ''], [], []],
        'numeric ?lang=' => [['lang' => '0'], [], []],
        'every source unusable at once' => [
            ['lang' => 'fr'],
            ['X-Locale' => 'xx', 'Accept-Language' => 'de-DE, fr;q=0.8'],
            ['locale' => 'de'],
        ],
    ]);

    it('falls through to the next source instead of straight to the default', function () {
        // ?lang=fr is unusable, so X-Locale decides - not config.
        $this->get('/_test/locale/web?lang=fr', ['X-Locale' => 'en'])
            ->assertJsonPath('app', 'en');
    });

    it('lands on the default when every source is unusable', function (string $uri) {
        // The default is deliberately moved off the head of the supported list so
        // that landing on "en" cannot be confused with silently picking supported[0].
        config(['localization.default' => 'en']);

        $this->withUnencryptedCookie('locale', 'de')
            ->get($uri.'?lang=fr', ['X-Locale' => 'xx'])
            ->assertExactJson(['app' => 'en', 'carbon' => 'en']);
    })->with('stacks');
});

describe('regional tags', function () {
    it('reduces a regional tag in ?lang= to its base language', function (string $tag, string $expected) {
        $this->get('/_test/locale/web?lang='.urlencode($tag))
            ->assertExactJson(['app' => $expected, 'carbon' => $expected]);
    })->with([
        'es-ES' => ['es-ES', 'es'],
        'en-GB' => ['en-GB', 'en'],
        'en_US' => ['en_US', 'en'],
        'es_MX' => ['es_MX', 'es'],
        'uppercase EN' => ['EN', 'en'],
        'mixed case Es-es' => ['Es-es', 'es'],
    ]);

    it('reduces a regional tag coming from the X-Locale header', function () {
        $this->get('/_test/locale/web', ['X-Locale' => 'en_US'])
            ->assertJsonPath('app', 'en');
    });

    it('reduces a regional tag coming from the locale cookie', function () {
        $this->withUnencryptedCookie('locale', 'en-GB')
            ->get('/_test/locale/web')
            ->assertJsonPath('app', 'en');
    });

});

describe('the locale cookie', function () {
    it('remembers an explicit choice made with ?lang=', function () {
        $this->get('/_test/locale/web?lang=en')
            ->assertPlainCookie('locale', 'en');
    });

    it('queues the cookie so the frontend can both read and rewrite it', function () {
        /*
        | This cookie is a display preference with two writers by design: Laravel
        | queues it here on ?lang=, and the browser's changeLocale() writes the same
        | name by hand when the language switcher is used. That is why it is
        | unencrypted - it is listed in encryptCookies(except:), and SetLocale reads
        | it before cookies are decrypted - and why it must not be HttpOnly.
        |
        | HttpOnly is asserted negatively and on purpose. A browser silently discards
        | a document.cookie write aimed at an existing HttpOnly cookie: no exception,
        | no console warning, the switcher simply reverts on the next page load.
        | Nothing else in this suite can see that, so if this expectation is ever
        | relaxed the defect becomes invisible again.
        */
        $response = $this->get('/_test/locale/web?lang=en');

        $cookie = collect($response->headers->getCookies())
            ->first(fn (Cookie $cookie) => $cookie->getName() === 'locale');

        expect($cookie)->not->toBeNull()
            ->and($cookie->isHttpOnly())->toBeFalse()
            // Raw on the wire: an encrypted value would be a base64 payload, not "en".
            ->and($cookie->getValue())->toBe('en')
            // The rest of the attribute set, pinned so that a change to any one of
            // them has to be a deliberate edit to this list.
            ->and($cookie->getPath())->toBe('/')
            ->and($cookie->getDomain())->toBeNull()
            ->and($cookie->isSecure())->toBeFalse()
            ->and($cookie->getSameSite())->toBe('lax')
            // Max-Age is derived from the queue time, so it is allowed to have lost a
            // second or two to the clock by the time it is read back here.
            ->and($cookie->getMaxAge())->toBeGreaterThan((int) config('localization.cookie_lifetime') * 60 - 5)
            ->and($cookie->getMaxAge())->toBeLessThanOrEqual((int) config('localization.cookie_lifetime') * 60);

        // The browser-facing truth: whatever the object says, the rendered header is
        // what decides whether document.cookie can touch this name.
        expect(strtolower((string) $cookie))->not->toContain('httponly');
    });

    it('remembers the normalised base tag, not the regional tag that was sent', function () {
        $this->get('/_test/locale/web?lang=en-GB')
            ->assertPlainCookie('locale', 'en');
    });

    it('does not remember a locale taken from the X-Locale header', function (string $uri) {
        // A native client owns its own preference and repeats the header on every
        // call, so persisting it server-side would only create a stale second source.
        $this->get($uri, ['X-Locale' => 'en'])
            ->assertCookieMissing('locale');
    })->with('stacks');

    it('queues nothing for a request whose only stated preference is ignored', function (string $uri) {
        // Accept-Language is not a source, so a request carrying only that header
        // has made no explicit choice and there is nothing to remember.
        $this->get($uri, ['Accept-Language' => 'en-GB,en;q=0.9'])
            ->assertCookieMissing('locale');
    })->with('stacks');

    it('does not re-queue the cookie it just read', function () {
        $this->withUnencryptedCookie('locale', 'en')
            ->get('/_test/locale/web')
            ->assertCookieMissing('locale');
    });

    it('does not remember an unsupported explicit choice', function (string $uri) {
        $this->get($uri.'?lang=fr')
            ->assertCookieMissing('locale');
    })->with('stacks');

    it('remembers an explicit choice made against the api stack from the browser', function () {
        // AddQueuedCookiesToResponse lives in the web group only. On /api the cookie
        // reaches the browser through Sanctum's stateful frontend path, which is the
        // only api caller that has a cookie jar in the first place.
        $this->get('/_test/locale/api?lang=en', [
            'Origin' => 'http://localhost',
            'Referer' => 'http://localhost/',
        ])->assertPlainCookie('locale', 'en');
    });

    it('drops the queued cookie for a stateless api caller', function () {
        // Documented, not desired: a token client sending ?lang= gets the locale it
        // asked for on this response but nothing to carry it to the next one. Such a
        // client is expected to use X-Locale, which is deliberately not persisted.
        $this->get('/_test/locale/api?lang=en')
            ->assertJsonPath('app', 'en')
            ->assertCookieMissing('locale');
    });
});

describe('Accept-Language is not a source', function () {
    /*
    | The product is Spanish-first by decision, not by omission. An English browser
    | landing on a Spanish business's booking page is served Spanish, because the
    | page belongs to that business and not to the visitor's browser settings:
    | language is an explicit choice a person makes - ?lang= today, a switcher
    | shortly - never an inference drawn from a header they never consciously set.
    |
    | These are the assertions that would fail if negotiation were reintroduced,
    | which is the only reason the header is mentioned in this file at all.
    */

    it('answers an english browser in spanish', function (string $uri) {
        $this->get($uri, ['Accept-Language' => 'en-US,en;q=0.9'])
            ->assertOk()
            ->assertExactJson(['app' => 'es', 'carbon' => 'es']);
    })->with('stacks');

    it('receives the header it ignores', function () {
        // Every other assertion in this block expects "es", which is also what a
        // request that never carried the header would produce - so on its own the
        // block could pass vacuously if the header were being dropped in transit.
        // Echoing it back proves the application really sees English and answers
        // Spanish anyway.
        Route::middleware('web')->get('/_test/locale/echo', fn (Request $request) => [
            'received' => $request->header('Accept-Language'),
            'app' => App::getLocale(),
        ]);

        $this->get('/_test/locale/echo', ['Accept-Language' => 'en-US,en;q=0.9'])
            ->assertExactJson(['received' => 'en-US,en;q=0.9', 'app' => 'es']);
    });

    it('ignores the header however it is phrased', function (string $header) {
        $this->get('/_test/locale/web', ['Accept-Language' => $header])
            ->assertJsonPath('app', 'es');
    })->with([
        'bare language' => 'en',
        'regional tag' => 'en-GB',
        'underscored tag' => 'en_US',
        'weighted list' => 'en-US,en;q=0.9',
        'english outranking spanish' => 'es;q=0.4,en;q=0.9',
        'english behind unsupported languages' => 'de-DE,fr;q=0.9,en;q=0.5',
    ]);

    it('does not let the header override an explicit choice', function () {
        $this->get('/_test/locale/web?lang=es', ['Accept-Language' => 'en-US,en;q=0.9'])
            ->assertJsonPath('app', 'es');
    });

    it('does not let the header override the remembered choice in the cookie', function () {
        $this->withUnencryptedCookie('locale', 'en')
            ->get('/_test/locale/web', ['Accept-Language' => 'es-ES,es;q=0.9'])
            ->assertJsonPath('app', 'en');
    });

    it('does not let the header stand in for an unusable explicit choice', function () {
        // ?lang=fr is discarded, and resolution continues to the cookie and then the
        // default - it must not quietly land on the header sitting underneath.
        $this->get('/_test/locale/web?lang=fr', ['Accept-Language' => 'en-US,en;q=0.9'])
            ->assertJsonPath('app', 'es');
    });
});
