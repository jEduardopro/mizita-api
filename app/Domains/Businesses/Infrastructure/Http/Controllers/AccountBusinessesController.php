<?php

declare(strict_types=1);

namespace App\Domains\Businesses\Infrastructure\Http\Controllers;

use App\Domains\Businesses\Application\Dtos\ListAccountBusinessesInput;
use App\Domains\Businesses\Application\UseCases\ListAccountBusinesses;
use App\Domains\Businesses\Infrastructure\Http\Resources\BusinessResource;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponder;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

final class AccountBusinessesController extends Controller
{
    public function __invoke(
        Request $request,
        ListAccountBusinesses $listAccountBusinesses,
        ApiResponder $responder,
    ): JsonResponse {
        /** @var User $account */
        $account = $request->user();

        try {
            $response = $listAccountBusinesses->handle(
                new ListAccountBusinessesInput($account->uuid),
            );

            if ($response->failed()) {
                return $responder->failure($response->error(), $response->warnings());
            }

            return $responder->success(
                $response,
                BusinessResource::collection($response->value()),
                Response::HTTP_OK,
            );
        } catch (Throwable $unexpected) {
            return $responder->unexpected($request, $unexpected);
        }
    }
}
