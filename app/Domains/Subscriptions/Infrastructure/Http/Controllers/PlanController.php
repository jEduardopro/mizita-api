<?php

declare(strict_types=1);

namespace App\Domains\Subscriptions\Infrastructure\Http\Controllers;

use App\Domains\Subscriptions\Application\UseCases\ListPlans;
use App\Domains\Subscriptions\Infrastructure\Http\Resources\PlanResource;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

final class PlanController extends Controller
{
    public function index(
        Request $request,
        ListPlans $listPlans,
        ApiResponder $responder,
    ): JsonResponse {
        try {
            $response = $listPlans->handle();

            if ($response->failed()) {
                return $responder->failure($response->error(), $response->warnings());
            }

            return $responder->success(
                $response,
                PlanResource::collection($response->value()),
                Response::HTTP_OK,
            );
        } catch (Throwable $unexpected) {
            return $responder->unexpected($request, $unexpected);
        }
    }
}
