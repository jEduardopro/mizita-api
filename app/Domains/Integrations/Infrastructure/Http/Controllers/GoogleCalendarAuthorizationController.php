<?php

declare(strict_types=1);

namespace App\Domains\Integrations\Infrastructure\Http\Controllers;

use App\Domains\Integrations\Application\Dtos\StartCalendarAuthorizationInput;
use App\Domains\Integrations\Application\UseCases\StartCalendarAuthorization;
use App\Domains\Integrations\Infrastructure\Http\Resources\AuthorizationUrlResource;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponder;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

final class GoogleCalendarAuthorizationController extends Controller
{
    public function store(
        Request $request,
        StartCalendarAuthorization $startAuthorization,
        ApiResponder $responder,
    ): JsonResponse {
        /** @var User $account */
        $account = $request->user();

        try {
            $response = $startAuthorization->handle(new StartCalendarAuthorizationInput($account->uuid));

            if ($response->failed()) {
                return $responder->failure($response->error(), $response->warnings());
            }

            return $responder->success(
                $response,
                AuthorizationUrlResource::make($response->value()),
                Response::HTTP_CREATED,
            );
        } catch (Throwable $unexpected) {
            return $responder->unexpected($request, $unexpected);
        }
    }
}
