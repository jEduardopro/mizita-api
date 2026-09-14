<?php

declare(strict_types=1);

namespace App\Domains\Businesses\Infrastructure\Http\Controllers;

use App\Domains\Businesses\Application\Dtos\OnboardBusinessInput;
use App\Domains\Businesses\Application\UseCases\OnboardBusiness;
use App\Domains\Businesses\Infrastructure\Http\Requests\CreateBusinessRequest;
use App\Domains\Businesses\Infrastructure\Http\Resources\BusinessResource;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponder;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

final class BusinessController extends Controller
{
    public function store(
        CreateBusinessRequest $request,
        OnboardBusiness $onboardBusiness,
        ApiResponder $responder,
    ): JsonResponse {
        /** @var User $owner */
        $owner = $request->user();

        try {
            $response = $onboardBusiness->handle(
                OnboardBusinessInput::fromRequest($request->validated(), $owner->uuid),
            );

            if ($response->failed()) {
                return $responder->failure($response->error(), $response->warnings());
            }

            return $responder->success(
                $response,
                BusinessResource::make($response->value()),
                Response::HTTP_CREATED,
            );
        } catch (Throwable $unexpected) {
            return $responder->unexpected($request, $unexpected);
        }
    }
}
