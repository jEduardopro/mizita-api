<?php

declare(strict_types=1);

namespace App\Domains\PublicCatalog\Infrastructure\Http\Controllers;

use App\Domains\PublicCatalog\Application\Dtos\ListPublicStatesInput;
use App\Domains\PublicCatalog\Application\UseCases\ListPublicStates;
use App\Domains\PublicCatalog\Infrastructure\Http\Requests\ListPublicStatesRequest;
use App\Domains\PublicCatalog\Infrastructure\Http\Resources\PublicStateResource;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponder;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

final class PublicStateController extends Controller
{
    public function index(
        ListPublicStatesRequest $request,
        ListPublicStates $listPublicStates,
        ApiResponder $responder,
    ): Response {
        try {
            $response = $listPublicStates->handle(ListPublicStatesInput::fromRequest($request->validated()));

            if ($response->failed()) {
                return $responder->failure($response->error(), $response->warnings());
            }

            return $responder->success(
                $response,
                PublicStateResource::collection($response->value()),
                Response::HTTP_OK,
            );
        } catch (Throwable $unexpected) {
            return $responder->unexpected($request, $unexpected);
        }
    }
}
