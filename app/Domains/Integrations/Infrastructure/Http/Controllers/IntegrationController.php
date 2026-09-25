<?php

declare(strict_types=1);

namespace App\Domains\Integrations\Infrastructure\Http\Controllers;

use App\Domains\Integrations\Application\Dtos\ListIntegrationsInput;
use App\Domains\Integrations\Application\UseCases\ListIntegrations;
use App\Domains\Integrations\Infrastructure\Http\Resources\IntegrationResource;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponder;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

final class IntegrationController extends Controller
{
    public function index(
        Request $request,
        ListIntegrations $listIntegrations,
        ApiResponder $responder,
    ): JsonResponse {
        /** @var User $account */
        $account = $request->user();

        try {
            $response = $listIntegrations->handle(new ListIntegrationsInput($account->uuid));

            if ($response->failed()) {
                return $responder->failure($response->error(), $response->warnings());
            }

            return $responder->success(
                $response,
                IntegrationResource::collection($response->value()),
                Response::HTTP_OK,
            );
        } catch (Throwable $unexpected) {
            return $responder->unexpected($request, $unexpected);
        }
    }
}
