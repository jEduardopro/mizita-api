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
 * Accept-Language is deliberately absent from the resolution chain, and must
 * stay absent. The product is Spanish-first: an English browser landing on a
 * Spanish business's booking page is still served Spanish, because the page
 * belongs to that business and not to the visitor's browser settings. Language
 * is an explicit choice a person makes, never an inference from headers they
 * never consciously set.
 *
 * ?lang= only persists the cookie on the web stack: AddQueuedCookiesToResponse
 * is in the web group only, so a stateless caller states its preference with
 * X-Locale on every call. That is intended, not a bug.
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
            // Deliberately not HttpOnly, and it must stay that way. This cookie
            // is a display preference, not a credential, and it has two writers
            // by design: Laravel here, and changeLocale() in lib/i18n.ts. A
            // browser silently ignores a document.cookie write onto an existing
            // HttpOnly cookie, so "hardening" this would let only one writer
            // ever win - the switcher would appear to work, then revert on the
            // next page load, with no error anywhere.
            //
            // Queued as a Cookie instance on purpose: CookieJar::queue() is
            // variadic and forwards array_values($parameters) to make(), which
            // drops the keys - so passing httpOnly: false to queue() directly
            // is silently a no-op.
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
     * The locale ends up in file paths under lang/, so a candidate outside the
     * supported list is ignored rather than trusted.
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

    /** es-ES and es_ES both become es, en-GB becomes en. */
    private function baseTag(string $tag): string
    {
        return strtolower(preg_split('/[-_]/', $tag, 2)[0]);
    }
}
