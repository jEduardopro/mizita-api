<?php

declare(strict_types=1);

use App\Http\Middleware\HandlePreferences;
use App\Http\Preferences\CookiePreferences;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

uses(TestCase::class);

/**
 * @param  array<string, mixed>  $cookies
 */
function preferencesRequest(array $cookies = []): Request
{
    return Request::create('/', 'GET', [], $cookies);
}

function handlePreferences(Request $request, ?Closure $next = null): Response
{
    return (new HandlePreferences(new CookiePreferences))
        ->handle($request, $next ?? fn () => new Response);
}

describe('sharing the appearance with the root view', function () {
    it('shares the stored appearance', function (string $stored) {
        handlePreferences(preferencesRequest(['appearance' => $stored]));

        expect(View::shared('appearance'))->toBe($stored);
    })->with(['light', 'dark', 'system']);

    it('shares the configured default when the request carries no cookie', function () {
        config(['preferences.appearance.default' => 'dark']);

        handlePreferences(preferencesRequest());

        expect(View::shared('appearance'))->toBe('dark');
    });

    it('shares the configured default when the stored value is unusable', function () {
        handlePreferences(preferencesRequest(['appearance' => 'blue']));

        expect(View::shared('appearance'))->toBe('light');
    });

    it('shares a plain string the blade template can compare, not the enum', function () {
        handlePreferences(preferencesRequest(['appearance' => 'dark']));

        expect(View::shared('appearance'))->toBeString();
    });

    it('shares the appearance before the rest of the stack runs', function () {
        $sharedDuringNext = null;

        handlePreferences(preferencesRequest(['appearance' => 'dark']), function () use (&$sharedDuringNext) {
            $sharedDuringNext = View::shared('appearance');

            return new Response;
        });

        expect($sharedDuringNext)->toBe('dark');
    });
});

describe('passing the request along', function () {
    it('returns the response the rest of the stack produced', function () {
        $expected = new Response('the page', 418);

        expect(handlePreferences(preferencesRequest(), fn () => $expected))->toBe($expected);
    });

    it('hands the untouched request to the rest of the stack', function () {
        $request = preferencesRequest(['appearance' => 'dark']);

        $received = null;

        handlePreferences($request, function (Request $passed) use (&$received) {
            $received = $passed;

            return new Response;
        });

        expect($received)->toBe($request);
    });

    it('reads the appearance from the request it was given', function () {
        $preferences = new CookiePreferences;

        (new HandlePreferences($preferences))->handle(
            preferencesRequest(['appearance' => 'system']),
            fn () => new Response,
        );

        expect(View::shared('appearance'))->toBe('system');
    });
});

describe('the cookie is the client to write, never the middleware', function () {
    it('queues no cookie when the request stored an appearance', function () {
        handlePreferences(preferencesRequest(['appearance' => 'dark']));

        expect(Cookie::getQueuedCookies())->toBeEmpty();
    });

    it('queues no cookie when it fell back to the default', function () {
        handlePreferences(preferencesRequest());

        expect(Cookie::getQueuedCookies())->toBeEmpty();
    });

    it('sets no cookie on the response it returns', function () {
        $response = handlePreferences(preferencesRequest());

        expect($response->headers->getCookies())->toBeEmpty();
    });
});
