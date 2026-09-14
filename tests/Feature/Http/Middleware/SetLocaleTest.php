<?php

declare(strict_types=1);

use App\Http\Middleware\SetLocale;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Response;

beforeEach(function () {
    $probe = fn () => [
        'app' => App::getLocale(),
        'carbon' => Carbon::getLocale(),
    ];

    Route::middleware('web')->get('/_test/locale/web', $probe);
    Route::middleware('api')->get('/_test/locale/api', $probe);

    $this->withHeader('Accept-Language', '');
});

afterEach(function () {
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
    it('ignores an unsupported candidate and falls back to the default', function (array $query, array $headers, array $cookies) {
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
        $this->get('/_test/locale/web?lang=fr', ['X-Locale' => 'en'])
            ->assertJsonPath('app', 'en');
    });

    it('lands on the default when every source is unusable', function (string $uri) {
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
        $response = $this->get('/_test/locale/web?lang=en');

        $cookie = collect($response->headers->getCookies())
            ->first(fn (Cookie $cookie) => $cookie->getName() === 'locale');

        expect($cookie)->not->toBeNull()
            ->and($cookie->isHttpOnly())->toBeFalse()
            ->and($cookie->getValue())->toBe('en')
            ->and($cookie->getPath())->toBe('/')
            ->and($cookie->getDomain())->toBeNull()
            ->and($cookie->isSecure())->toBeFalse()
            ->and($cookie->getSameSite())->toBe('lax')
            ->and($cookie->getMaxAge())->toBeGreaterThan((int) config('localization.cookie_lifetime') * 60 - 5)
            ->and($cookie->getMaxAge())->toBeLessThanOrEqual((int) config('localization.cookie_lifetime') * 60);

        expect(strtolower((string) $cookie))->not->toContain('httponly');
    });

    it('remembers the normalised base tag, not the regional tag that was sent', function () {
        $this->get('/_test/locale/web?lang=en-GB')
            ->assertPlainCookie('locale', 'en');
    });

    it('does not remember a locale taken from the X-Locale header', function (string $uri) {
        $this->get($uri, ['X-Locale' => 'en'])
            ->assertCookieMissing('locale');
    })->with('stacks');

    it('queues nothing for a request whose only stated preference is ignored', function (string $uri) {
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
        $this->get('/_test/locale/api?lang=en', [
            'Origin' => 'http://localhost',
            'Referer' => 'http://localhost/',
        ])->assertPlainCookie('locale', 'en');
    });

    it('drops the queued cookie for a stateless api caller', function () {
        $this->get('/_test/locale/api?lang=en')
            ->assertJsonPath('app', 'en')
            ->assertCookieMissing('locale');
    });
});

describe('Accept-Language is not a source', function () {
    it('answers an english browser in spanish', function (string $uri) {
        $this->get($uri, ['Accept-Language' => 'en-US,en;q=0.9'])
            ->assertOk()
            ->assertExactJson(['app' => 'es', 'carbon' => 'es']);
    })->with('stacks');

    it('receives the header it ignores', function () {
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
        $this->get('/_test/locale/web?lang=fr', ['Accept-Language' => 'en-US,en;q=0.9'])
            ->assertJsonPath('app', 'es');
    });
});
