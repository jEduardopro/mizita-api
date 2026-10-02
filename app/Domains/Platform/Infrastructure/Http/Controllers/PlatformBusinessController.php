<?php

declare(strict_types=1);

namespace App\Domains\Platform\Infrastructure\Http\Controllers;

use App\Domains\Platform\Application\Dtos\ListPlatformBusinessesInput;
use App\Domains\Platform\Application\UseCases\ListPlatformBusinesses;
use App\Domains\Platform\Infrastructure\Http\Requests\ListPlatformBusinessesRequest;
use App\Domains\Platform\Infrastructure\Http\Resources\PlatformBusinessResource;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponder;
use App\Http\Responses\PaginatedCollection;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

final class PlatformBusinessController extends Controller
{
    public function index(
        ListPlatformBusinessesRequest $request,
        ListPlatformBusinesses $listPlatformBusinesses,
        ApiResponder $responder,
    ): Response {
        try {
            $response = $listPlatformBusinesses->handle(
                ListPlatformBusinessesInput::fromRequest($request->validated()),
            );

            if ($response->failed()) {
                return $responder->failure($response->error(), $response->warnings());
            }

            return $responder->success(
                $response,
                PaginatedCollection::of($response->value(), PlatformBusinessResource::class),
                Response::HTTP_OK,
            );
        } catch (Throwable $unexpected) {
            return $responder->unexpected($request, $unexpected);
        }
    }
}
