<?php

declare(strict_types=1);

use App\Http\Responses\JsonFailureRendering;
use Illuminate\Http\Request;

/**
 * @param  array<string, string>  $headers
 */
function jsonRenderingRequest(string $path, array $headers = []): Request
{
    $request = Request::create($path, 'POST');

    foreach ($headers as $name => $value) {
        $request->headers->set($name, $value);
    }

    return $request;
}

describe('deciding whether a failure answers with json', function () {
    it('answers json for the request shapes the api owns', function (string $path, array $headers) {
        expect(JsonFailureRendering::appliesTo(jsonRenderingRequest($path, $headers)))->toBeTrue();
    })->with([
        'an api path' => ['/api/businesses', []],
        'a nested api path' => ['/api/businesses/name-availability', []],
        'an api path with no accept header at all' => ['/api/user', []],
        'an api path that asks for html' => ['/api/businesses', ['Accept' => 'text/html']],
        'a web path that asks for json' => ['/onboarding', ['Accept' => 'application/json']],
        'a web path that asks for json in a quality list' => ['/onboarding', ['Accept' => 'application/json, text/plain, */*']],
        'a web path from an ajax caller accepting anything' => ['/onboarding', ['X-Requested-With' => 'XMLHttpRequest', 'Accept' => '*/*']],
    ]);

    it('hands the request back to laravel when nothing asked for json', function (string $path, array $headers) {
        expect(JsonFailureRendering::appliesTo(jsonRenderingRequest($path, $headers)))->toBeFalse();
    })->with([
        'a plain web request' => ['/onboarding', []],
        'a web request asking for html' => ['/onboarding', ['Accept' => 'text/html']],
        'a web request asking for a quality list without json' => ['/onboarding', ['Accept' => 'text/html, text/plain']],
        'the web root' => ['/', []],
    ]);

    it('matches the api route pattern, never a path that merely contains the word', function (string $path) {
        expect(JsonFailureRendering::appliesTo(jsonRenderingRequest($path)))->toBeFalse();
    })->with([
        'a web path starting with the same letters' => '/rapid',
        'a web path ending in api' => '/dashboard/api',
        'a web path carrying api in the middle' => '/business/api/settings',
        'a web path prefixed with the word' => '/apidocs/businesses',
    ]);

    it('suppresses json for an inertia visit, whatever else the request asked for', function (string $path, array $headers) {
        expect(JsonFailureRendering::appliesTo(jsonRenderingRequest($path, $headers + ['X-Inertia' => 'true'])))
            ->toBeFalse();
    })->with([
        'an inertia visit to an api path' => ['/api/businesses', []],
        'an inertia visit to a nested api path' => ['/api/businesses/name-availability', []],
        'an inertia visit to a web path asking for json' => ['/onboarding', ['Accept' => 'application/json']],
        'an inertia visit to a web path asking for json in a quality list' => ['/onboarding', ['Accept' => 'application/json, text/plain, */*']],
        'an inertia visit from an ajax caller accepting anything' => ['/onboarding', ['X-Requested-With' => 'XMLHttpRequest', 'Accept' => '*/*']],
        'an inertia visit to a web path' => ['/onboarding', []],
        'an inertia visit asking for html' => ['/onboarding', ['Accept' => 'text/html']],
    ]);

    it('suppresses json on the presence of the inertia header, not on its value', function (string $value) {
        expect(JsonFailureRendering::appliesTo(jsonRenderingRequest('/api/businesses', ['X-Inertia' => $value])))
            ->toBeFalse();
    })->with([
        'the value inertia sends' => 'true',
        'a falsy value' => 'false',
        'an empty value' => '',
    ]);
});
