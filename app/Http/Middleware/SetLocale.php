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
 *  4. config('localization.default'), which is Spanish.
 *
 * Accept-Language is deliberately absent from that chain, and must stay absent.
 * The product is Spanish-first: an English browser landing on a Spanish
 * business's booking page should still be served Spanish, because the page
 * belongs to that business and not to the visitor's browser settings. Language
 * is therefore an explicit choice a person makes - today the ?lang= parameter,
 * shortly a language switcher in the interface - never an inference drawn from
 * headers they never consciously set. Negotiating Accept-Language would silently
 * override that decision, so this is a product decision rather than a gap to
 * fill in.
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
            ?? config('localization.default');

        App::setLocale($locale);

        // Keeps formatted dates ("12 de marzo") in the same language as the
        // translated strings around them.
        Carbon::setLocale($locale);

        if ($explicit !== null) {
            // Deliberately not HttpOnly, and it must stay that way. This cookie is
            // a display preference, not a credential - the same reasoning that
            // already excludes it from encryptCookies() in bootstrap/app.php - and
            // it has two writers by design: Laravel here on ?lang=, and
            // changeLocale() in resources/js/lib/i18n.ts. A browser silently
            // ignores a document.cookie write onto an existing HttpOnly cookie, so
            // "hardening" this would let only one of those writers ever win: the
            // language switcher would appear to work, then revert on the next page
            // load, with no error anywhere. It would also leave i18next's own
            // cookie detector blind to a cookie Laravel had set.
            //
            // Queued as a Cookie instance on purpose: CookieJar::queue() is
            // variadic and forwards array_values($parameters) to make(), which
            // drops the keys - so passing httpOnly: false to queue() directly is
            // silently a no-op. Naming it on make() keeps $path, $domain and
            // $secure at their framework defaults.
            Cookie::queue(Cookie::make(
                config('localization.cookie'),
                $explicit,
                (int) config('localization.cookie_lifetime'),
                httpOnly: false,
            ));
        }

        return $next($request);
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
