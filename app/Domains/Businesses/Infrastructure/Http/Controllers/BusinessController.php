<?php

declare(strict_types=1);

namespace App\Domains\Businesses\Infrastructure\Http\Controllers;

use App\Domains\Businesses\Application\Dtos\OnboardBusinessInput;
use App\Domains\Businesses\Application\UseCases\OnboardBusiness;
use App\Domains\Businesses\Infrastructure\Http\Requests\CreateBusinessRequest;
use App\Domains\Businesses\Infrastructure\Http\Resources\BusinessResource;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

final class BusinessController extends Controller
{
    public function store(CreateBusinessRequest $request, OnboardBusiness $onboardBusiness): JsonResponse
    {
        /** @var User $owner */
        $owner = $request->user();

        $business = $onboardBusiness->handle(
            OnboardBusinessInput::fromRequest($request->validated(), $owner->uuid),
        );

        return BusinessResource::make($business)
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }
}
