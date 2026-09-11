<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Cookie;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves the locale the request is answered in, for every caller the platform
 * has: the Inertia web pages, the browser calling /api, and the future native
 * client. The first source that yields a supported locale wins:
 *
 *  1. ?lang= - an explicit choice, so it is also remembered in a cookie.
 *  2. X-Locale - what a native client sends; deliberately not persisted, the
 *     client owns its own preference and repeats the header on every call.
 *  3. The locale cookie - the remembered explicit choice.
 *  4. Accept-Language, negotiated against the supported list.
 *  5. config('localization.default').
 *
 * A candidate that is not in config('localization.supported') is ignored and
 * resolution falls through to the next source: the locale ends up in file paths
 * under lang/, so it is never taken from the request without being checked.
 *
 * Note that ?lang= only persists the cookie on the web stack. On api the queued
 * cookie is dropped, because AddQueuedCookiesToResponse is in the web group
 * only - so a stateless caller states its preference with X-Locale on every
 * call rather than expecting ?lang= to stick. That is intended, not a bug.
 *
 * This middleware knows nothing about businesses, users or any domain - it only
 * reads the request and sets the process locale.
 */
final class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var list<string> $supported */
        $supported = config('localization.supported');

        $explicit = $this->supported($request->query('lang'), $supported);

        $locale = $explicit
            ?? $this->supported($request->header('X-Locale'), $supported)
            ?? $this->supported($request->cookie(config('localization.cookie')), $supported)
            ?? $this->fromAcceptLanguage($request, $supported)
            ?? config('localization.default');

        App::setLocale($locale);

        // Keeps formatted dates ("12 de marzo") in the same language as the
        // translated strings around them.
        Carbon::setLocale($locale);

        if ($explicit !== null) {
            Cookie::queue(
                config('localization.cookie'),
                $explicit,
                (int) config('localization.cookie_lifetime'),
            );
        }

        return $next($request);
    }

    /**
     * Negotiates the Accept-Language header against the supported locales.
     *
     * @param  list<string>  $supported
     */
    private function fromAcceptLanguage(Request $request, array $supported): ?string
    {
        if (! $request->hasHeader('Accept-Language')) {
            return null;
        }

        // Symfony returns the first entry of the supplied list when nothing in
        // the header is acceptable, which would make this source always match.
        // Checking what was actually offered keeps the fall-through honest.
        $offered = array_map($this->baseTag(...), $request->getLanguages());

        if (array_intersect($offered, $supported) === []) {
            return null;
        }

        return $this->supported($request->getPreferredLanguage($supported), $supported);
    }

    /**
     * Returns the candidate reduced to a supported base tag, or null.
     *
     * @param  list<string>  $supported
     */
    private function supported(mixed $candidate, array $supported): ?string
    {
        if (! is_string($candidate) || $candidate === '') {
            return null;
        }

        $locale = $this->baseTag($candidate);

        return in_array($locale, $supported, strict: true) ? $locale : null;
    }

    /**
     * Reduces a regional tag to the language it belongs to: es-ES and es_ES
     * both become es, en-GB becomes en.
     */
    private function baseTag(string $tag): string
    {
        return strtolower(preg_split('/[-_]/', $tag, 2)[0]);
    }
}
