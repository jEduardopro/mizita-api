<?php

declare(strict_types=1);

namespace App\Domains\Businesses\Infrastructure\Http\Controllers;

use App\Domains\Businesses\Application\Dtos\OnboardBusinessInput;
use App\Domains\Businesses\Application\UseCases\OnboardBusiness;
use App\Domains\Businesses\Infrastructure\Http\Requests\CreateBusinessRequest;
use App\Domains\Businesses\Infrastructure\Http\Resources\BusinessResource;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Shared\ValueObjects\CountryCode;
use App\Shared\ValueObjects\PhoneNumber;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

final class BusinessController extends Controller
{
    public function store(CreateBusinessRequest $request, OnboardBusiness $onboardBusiness): JsonResponse
    {
        /** @var User $owner */
        $owner = $request->user();

        $business = $onboardBusiness->handle(new OnboardBusinessInput(
            // The owner is who is signed in, never who the body says. A client
            // that could name the owner could hand a business to a stranger.
            ownerAccountId: $owner->uuid,
            name: $request->string('name')->toString(),
            timezone: $request->string('timezone')->toString(),
            industryId: $request->string('industry_id')->toString(),
            phone: $this->phoneFrom($request),
        ));

        return BusinessResource::make($business)
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * The number as a value object, or null when the owner skipped it.
     *
     * Both parts have already been validated, so building the value object here
     * cannot fail for anything a caller sent.
     */
    private function phoneFrom(CreateBusinessRequest $request): ?PhoneNumber
    {
        $phone = $request->array('phone');

        if ($phone === []) {
            return null;
        }

        return PhoneNumber::fromParts(
            CountryCode::from((string) $phone['country_code']),
            (string) $phone['national_number'],
        );
    }
}
