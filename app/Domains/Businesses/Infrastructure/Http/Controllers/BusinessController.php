<?php

declare(strict_types=1);

namespace App\Domains\Businesses\Infrastructure\Http\Controllers;

use App\Domains\Businesses\Application\Dtos\CreateBusinessInput;
use App\Domains\Businesses\Application\UseCases\CreateBusiness;
use App\Domains\Businesses\Infrastructure\Http\Requests\CreateBusinessRequest;
use App\Domains\Businesses\Infrastructure\Http\Resources\BusinessResource;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

final class BusinessController extends Controller
{
    public function store(CreateBusinessRequest $request, CreateBusiness $createBusiness): JsonResponse
    {
        $business = $createBusiness->handle(new CreateBusinessInput(
            name: $request->string('name')->toString(),
            slug: $request->string('slug')->toString(),
        ));

        return BusinessResource::make($business)
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }
}
