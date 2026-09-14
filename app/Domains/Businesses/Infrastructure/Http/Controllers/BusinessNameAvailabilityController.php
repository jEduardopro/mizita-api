<?php

declare(strict_types=1);

namespace App\Domains\Businesses\Infrastructure\Http\Controllers;

use App\Domains\Businesses\Application\Dtos\CheckBusinessNameAvailabilityInput;
use App\Domains\Businesses\Application\UseCases\CheckBusinessNameAvailability;
use App\Domains\Businesses\Infrastructure\Http\Requests\CheckBusinessNameAvailabilityRequest;
use App\Domains\Businesses\Infrastructure\Http\Resources\BusinessNameAvailabilityResource;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponder;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

final class BusinessNameAvailabilityController extends Controller
{
    public function __invoke(
        CheckBusinessNameAvailabilityRequest $request,
        CheckBusinessNameAvailability $checkAvailability,
        ApiResponder $responder,
    ): JsonResponse {
        try {
            $response = $checkAvailability->handle(
                CheckBusinessNameAvailabilityInput::fromRequest($request->validated()),
            );

            if ($response->failed()) {
                return $responder->failure($response->error(), $response->warnings());
            }

            return $responder->success(
                $response,
                BusinessNameAvailabilityResource::make($response->value()),
                Response::HTTP_OK,
            );
        } catch (Throwable $unexpected) {
            return $responder->unexpected($request, $unexpected);
        }
    }
}
