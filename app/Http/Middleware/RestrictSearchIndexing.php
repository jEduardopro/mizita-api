<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Http\Seo\RobotsDirective;
use App\Http\Seo\SearchIndexing;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class RestrictSearchIndexing
{
    public function __construct(private readonly SearchIndexing $indexing) {}

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($this->indexing->permitted()) {
            return $response;
        }

        $response->headers->set(RobotsDirective::HEADER, RobotsDirective::NoIndexNoFollow->value);

        return $response;
    }
}
