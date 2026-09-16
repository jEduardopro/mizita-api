<?php

declare(strict_types=1);

namespace App\Domains\Addresses\Infrastructure\Http\Controllers;

use App\Domains\Addresses\Application\Dtos\ListStatesInput;
use App\Domains\Addresses\Application\UseCases\ListStates;
use App\Domains\Addresses\Infrastructure\Http\Requests\ListStatesRequest;
use App\Domains\Addresses\Infrastructure\Http\Resources\StateResource;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponder;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

final class StateController extends Controller
{
    public function index(
        ListStatesRequest $request,
        ListStates $listStates,
        ApiResponder $responder,
    ): Response {
        try {
            $response = $listStates->handle(ListStatesInput::fromRequest($request->validated()));

            if ($response->failed()) {
                return $responder->failure($response->error(), $response->warnings());
            }

            return $responder->success(
                $response,
                StateResource::collection($response->value()),
                Response::HTTP_OK,
            );
        } catch (Throwable $unexpected) {
            return $responder->unexpected($request, $unexpected);
        }
    }
}
