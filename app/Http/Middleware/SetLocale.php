<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Cookie;
use Symfony\Component\HttpFoundation\Response;

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

        Carbon::setLocale($locale);

        if ($explicit !== null) {
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

    private function baseTag(string $tag): string
    {
        return strtolower(preg_split('/[-_]/', $tag, 2)[0]);
    }
}
