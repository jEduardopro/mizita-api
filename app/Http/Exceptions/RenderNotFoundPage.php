<?php

declare(strict_types=1);

namespace App\Http\Exceptions;

use App\Http\Responses\JsonFailureRendering;
use App\Http\Seo\RobotsDirective;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

final class RenderNotFoundPage
{
    private const PAGE = 'errors/not-found';

    public function __invoke(Request $request): ?Response
    {
        if (JsonFailureRendering::appliesTo($request) || ! $request->hasSession()) {
            return null;
        }

        $response = Inertia::render(self::PAGE)
            ->toResponse($request)
            ->setStatusCode(Response::HTTP_NOT_FOUND);

        $response->headers->set(RobotsDirective::HEADER, RobotsDirective::NoIndex->value);

        return $response;
    }
}
