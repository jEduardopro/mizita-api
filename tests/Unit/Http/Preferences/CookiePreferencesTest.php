<?php

declare(strict_types=1);

use App\Http\Preferences\Appearance;
use App\Http\Preferences\CookiePreferences;
use Illuminate\Http\Request;
use Tests\TestCase;

uses(TestCase::class);

/**
 * @param  array<string, mixed>  $cookies
 */
function requestCarryingCookies(array $cookies = []): Request
{
    return Request::create('/', 'GET', [], $cookies);
}

function storedAppearance(mixed $value): Request
{
    return requestCarryingCookies(['appearance' => $value]);
}

function storedSidebarState(mixed $value): Request
{
    return requestCarryingCookies(['sidebar_state' => $value]);
}

describe('reading the stored appearance', function () {
    it('returns the appearance the cookie stores', function (string $stored, Appearance $expected) {
        expect((new CookiePreferences)->appearance(storedAppearance($stored)))->toBe($expected);
    })->with([
        'light' => ['light', Appearance::Light],
        'dark' => ['dark', Appearance::Dark],
        'system' => ['system', Appearance::System],
    ]);

    it('falls back to the configured default when the request carries no cookie', function () {
        expect((new CookiePreferences)->appearance(requestCarryingCookies()))->toBe(Appearance::Light);
    });

    it('falls back to the configured default when the stored value is unusable', function (mixed $stored) {
        expect((new CookiePreferences)->appearance(storedAppearance($stored)))->toBe(Appearance::Light);
    })->with([
        'empty string' => '',
        'whitespace' => '   ',
        'unknown appearance' => 'blue',
        'wrong case' => 'Dark',
        'uppercase' => 'LIGHT',
        'trailing space' => 'dark ',
        'numeric' => '0',
        'another numeric' => '1',
        'the literal null' => 'null',
        'path traversal' => '../../etc/passwd',
        'absolute path' => '/etc/passwd',
        'null byte' => "dark\0",
        'cookie attribute injection' => 'dark; path=/',
        'html payload' => '<script>alert(1)</script>',
        'an overlong value' => 'dark'.str_repeat('a', 4096),
        'an array instead of a string' => [['dark']],
    ]);
});

describe('the configuration is the single source of truth', function () {
    it('takes the fallback from the configured default', function (string $default, Appearance $expected) {
        config(['preferences.appearance.default' => $default]);

        expect((new CookiePreferences)->appearance(requestCarryingCookies()))->toBe($expected);
    })->with([
        'light' => ['light', Appearance::Light],
        'dark' => ['dark', Appearance::Dark],
        'system' => ['system', Appearance::System],
    ]);

    it('falls back to the configured default when the stored value is unusable', function () {
        config(['preferences.appearance.default' => 'dark']);

        expect((new CookiePreferences)->appearance(storedAppearance('blue')))->toBe(Appearance::Dark);
    });

    it('prefers a usable stored value over the configured default', function () {
        config(['preferences.appearance.default' => 'dark']);

        expect((new CookiePreferences)->appearance(storedAppearance('light')))->toBe(Appearance::Light);
    });

    it('reads the cookie the configuration names', function () {
        config(['preferences.appearance.cookie' => 'mizita_appearance']);

        $request = requestCarryingCookies(['mizita_appearance' => 'dark']);

        expect((new CookiePreferences)->appearance($request))->toBe(Appearance::Dark);
    });

    it('ignores a cookie the configuration no longer names', function () {
        config(['preferences.appearance.cookie' => 'mizita_appearance']);

        expect((new CookiePreferences)->appearance(storedAppearance('dark')))->toBe(Appearance::Light);
    });

    it('ships a default that is a valid appearance', function () {
        expect(Appearance::tryFrom((string) config('preferences.appearance.default')))
            ->not->toBeNull();
    });
});

describe('reading the stored sidebar state', function () {
    it('returns the state the cookie stores', function (string $stored, bool $expected) {
        expect((new CookiePreferences)->sidebarOpen(storedSidebarState($stored)))->toBe($expected);
    })->with([
        'true' => ['true', true],
        'false' => ['false', false],
        'one' => ['1', true],
        'zero' => ['0', false],
        'on' => ['on', true],
        'off' => ['off', false],
        'yes' => ['yes', true],
        'no' => ['no', false],
    ]);

    it('returns a stored collapsed sidebar as false, not as the default', function () {
        expect((new CookiePreferences)->sidebarOpen(storedSidebarState('false')))->toBeFalse();
    });

    it('falls back to the configured default when the request carries no cookie', function () {
        expect((new CookiePreferences)->sidebarOpen(requestCarryingCookies()))->toBeTrue();
    });

    it('falls back to the configured default when the stored value is unusable', function (mixed $stored) {
        expect((new CookiePreferences)->sidebarOpen(storedSidebarState($stored)))->toBeTrue();
    })->with([
        'empty string' => '',
        'a word that is neither' => 'maybe',
        'the literal null' => 'null',
        'a number that is neither' => '2',
        'negative' => '-1',
        'null byte' => "true\0",
        'cookie attribute injection' => 'true; path=/',
        'html payload' => '<script>alert(1)</script>',
        'an overlong value' => str_repeat('a', 4096),
        'an array instead of a string' => [['false']],
    ]);

    it('takes the fallback from the configured default', function (bool $default) {
        config(['preferences.sidebar.default' => $default]);

        expect((new CookiePreferences)->sidebarOpen(requestCarryingCookies()))->toBe($default);
    })->with([
        'open' => true,
        'collapsed' => false,
    ]);

    it('falls back to a collapsed default when the stored value is unusable', function () {
        config(['preferences.sidebar.default' => false]);

        expect((new CookiePreferences)->sidebarOpen(storedSidebarState('maybe')))->toBeFalse();
    });

    it('prefers a usable stored value over the configured default', function () {
        config(['preferences.sidebar.default' => false]);

        expect((new CookiePreferences)->sidebarOpen(storedSidebarState('true')))->toBeTrue();
    });

    it('reads the cookie the configuration names', function () {
        config(['preferences.sidebar.cookie' => 'mizita_sidebar']);

        $request = requestCarryingCookies(['mizita_sidebar' => 'false']);

        expect((new CookiePreferences)->sidebarOpen($request))->toBeFalse();
    });

    it('ignores a cookie the configuration no longer names', function () {
        config(['preferences.sidebar.cookie' => 'mizita_sidebar']);

        expect((new CookiePreferences)->sidebarOpen(storedSidebarState('false')))->toBeTrue();
    });

    it('ships a default the provider can share as a boolean', function () {
        expect(config('preferences.sidebar.default'))->toBeBool();
    });
});

describe('memoisation', function () {
    it('resolves the appearance once and reuses it', function () {
        $preferences = new CookiePreferences;

        expect($preferences->appearance(storedAppearance('dark')))->toBe(Appearance::Dark)
            ->and($preferences->appearance(storedAppearance('light')))->toBe(Appearance::Dark);
    });

    it('keeps the memoised appearance when the configuration changes underneath it', function () {
        $preferences = new CookiePreferences;

        expect($preferences->appearance(requestCarryingCookies()))->toBe(Appearance::Light);

        config(['preferences.appearance.default' => 'dark']);

        expect($preferences->appearance(requestCarryingCookies()))->toBe(Appearance::Light);
    });

    it('memoises per instance, not globally', function () {
        (new CookiePreferences)->appearance(storedAppearance('dark'));

        expect((new CookiePreferences)->appearance(storedAppearance('light')))->toBe(Appearance::Light);
    });

    it('is shared across the request so every collaborator reads the same instance', function () {
        expect(app(CookiePreferences::class))->toBe(app(CookiePreferences::class));
    });

    it('resolves the sidebar state once and reuses it', function () {
        $preferences = new CookiePreferences;

        expect($preferences->sidebarOpen(storedSidebarState('false')))->toBeFalse()
            ->and($preferences->sidebarOpen(storedSidebarState('true')))->toBeFalse();
    });

    it('memoises a collapsed sidebar instead of resolving it again as the default', function () {
        $preferences = new CookiePreferences;

        expect($preferences->sidebarOpen(storedSidebarState('false')))->toBeFalse()
            ->and($preferences->sidebarOpen(requestCarryingCookies()))->toBeFalse();
    });

    it('memoises the sidebar state per instance, not globally', function () {
        (new CookiePreferences)->sidebarOpen(storedSidebarState('false'));

        expect((new CookiePreferences)->sidebarOpen(storedSidebarState('true')))->toBeTrue();
    });

    it('memoises each preference on its own', function () {
        $preferences = new CookiePreferences;
        $request = requestCarryingCookies(['appearance' => 'dark', 'sidebar_state' => 'false']);

        expect($preferences->appearance($request))->toBe(Appearance::Dark)
            ->and($preferences->sidebarOpen($request))->toBeFalse()
            ->and($preferences->appearance($request))->toBe(Appearance::Dark);
    });
});
