<?php

declare(strict_types=1);

namespace App\Domains\PublicCatalog\Infrastructure\Http\Controllers;

use App\Domains\PublicCatalog\Application\Dtos\ShowPublicAvailabilityInput;
use App\Domains\PublicCatalog\Application\UseCases\ShowPublicAvailability;
use App\Domains\PublicCatalog\Infrastructure\Http\Requests\PublicAvailabilityRequest;
use App\Domains\PublicCatalog\Infrastructure\Http\Resources\PublicAvailableDayResource;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponder;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

final class PublicAvailabilityController extends Controller
{
    public function show(
        PublicAvailabilityRequest $request,
        string $slug,
        ShowPublicAvailability $showPublicAvailability,
        ApiResponder $responder,
    ): Response {
        try {
            $response = $showPublicAvailability->handle(
                ShowPublicAvailabilityInput::fromRequest($request->validated(), $slug),
            );

            if ($response->failed()) {
                return $responder->failure($response->error(), $response->warnings());
            }

            return $responder->success(
                $response,
                PublicAvailableDayResource::collection($response->value()),
                Response::HTTP_OK,
            );
        } catch (Throwable $unexpected) {
            return $responder->unexpected($request, $unexpected);
        }
    }
}
