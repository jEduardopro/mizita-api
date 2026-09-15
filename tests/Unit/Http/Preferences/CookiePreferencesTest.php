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
});
