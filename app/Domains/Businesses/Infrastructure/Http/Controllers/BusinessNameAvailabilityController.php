<?php

declare(strict_types=1);

namespace App\Domains\Businesses\Infrastructure\Http\Controllers;

use App\Domains\Businesses\Application\Dtos\CheckBusinessNameAvailabilityInput;
use App\Domains\Businesses\Application\UseCases\CheckBusinessNameAvailability;
use App\Domains\Businesses\Infrastructure\Http\Requests\CheckBusinessNameAvailabilityRequest;
use App\Domains\Businesses\Infrastructure\Http\Resources\BusinessNameAvailabilityResource;
use App\Http\Controllers\Controller;

final class BusinessNameAvailabilityController extends Controller
{
    public function __invoke(
        CheckBusinessNameAvailabilityRequest $request,
        CheckBusinessNameAvailability $checkAvailability,
    ): BusinessNameAvailabilityResource {
        return BusinessNameAvailabilityResource::make($checkAvailability->handle(
            CheckBusinessNameAvailabilityInput::fromRequest($request->validated()),
        ));
    }
}
