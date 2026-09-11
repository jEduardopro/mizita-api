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
    // into every test request. Left alone it silently resolves every test to "en"
    // and makes the lower-priority sources untestable, so the baseline blanks it:
    // a test that is about Accept-Language states the header itself.
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

    it('negotiates the Accept-Language header', function (string $uri) {
        $this->get($uri, ['Accept-Language' => 'en-GB,en;q=0.9'])
            ->assertExactJson(['app' => 'en', 'carbon' => 'en']);
    })->with('stacks');

    it('falls back to the default when the request carries no Accept-Language header at all', function () {
        // Request::create() always synthesises the header, so the genuinely absent
        // case - a native client, curl - is only reachable by driving the
        // middleware directly.
        $request = Request::create('/');
        $request->headers->remove('Accept-Language');

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

    it('prefers the locale cookie over Accept-Language', function () {
        $this->withUnencryptedCookie('locale', 'en')
            ->get('/_test/locale/web', ['Accept-Language' => 'es-ES,es;q=0.9'])
            ->assertJsonPath('app', 'en');
    });

    it('prefers Accept-Language over the configured default', function () {
        $this->get('/_test/locale/web', ['Accept-Language' => 'en-GB,en;q=0.9'])
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

    it('lands on the default when Accept-Language offers nothing supported', function (string $uri) {
        // Symfony's getPreferredLanguage() returns the first entry of the list it is
        // handed when nothing in the header is acceptable. Without the guard in the
        // middleware, Accept-Language would therefore match on every request and pin
        // every unmatched caller to supported[0] - "es" - instead of the configured
        // default. Moving the default to "en" is what lets this assertion tell the
        // two apart; with the default left at "es" it would pass either way.
        config(['localization.default' => 'en']);

        $this->get($uri, ['Accept-Language' => 'de-DE, fr;q=0.8'])
            ->assertExactJson(['app' => 'en', 'carbon' => 'en']);
    })->with('stacks');

    it('still negotiates when the header mixes supported and unsupported languages', function () {
        config(['localization.default' => 'en']);

        $this->get('/_test/locale/web', ['Accept-Language' => 'de-DE,fr;q=0.9,es;q=0.5'])
            ->assertJsonPath('app', 'es');
    });

    it('honours the quality values when the header offers several supported languages', function () {
        $this->get('/_test/locale/web', ['Accept-Language' => 'es;q=0.4,en;q=0.9'])
            ->assertJsonPath('app', 'en');
    });
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

    it('reduces a regional tag coming from Accept-Language', function () {
        $this->get('/_test/locale/web', ['Accept-Language' => 'en-GB'])
            ->assertJsonPath('app', 'en');
    });
});

describe('the locale cookie', function () {
    it('remembers an explicit choice made with ?lang=', function () {
        $this->get('/_test/locale/web?lang=en')
            ->assertPlainCookie('locale', 'en');
    });

    it('queues the cookie unencrypted so the frontend can read it', function () {
        // config('localization.cookie') is listed in encryptCookies(except:): i18next
        // reads it from JavaScript, and SetLocale itself runs before cookies are
        // decrypted, so an encrypted value would be unusable at both ends.
        $response = $this->get('/_test/locale/web?lang=en');

        $cookie = collect($response->headers->getCookies())
            ->first(fn (Cookie $cookie) => $cookie->getName() === 'locale');

        expect($cookie)->not->toBeNull()
            // Raw on the wire: an encrypted value would be a base64 payload, not "en".
            ->and($cookie->getValue())->toBe('en');
    });

    it('remembers the normalised base tag, not the regional tag that was sent', function () {
        $this->get('/_test/locale/web?lang=en-GB')
            ->assertPlainCookie('locale', 'en');
    });

    it('does not remember a locale negotiated from the X-Locale header', function (string $uri) {
        // A native client owns its own preference and repeats the header on every
        // call, so persisting it server-side would only create a stale second source.
        $this->get($uri, ['X-Locale' => 'en'])
            ->assertCookieMissing('locale');
    })->with('stacks');

    it('does not remember a locale negotiated from Accept-Language', function (string $uri) {
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
