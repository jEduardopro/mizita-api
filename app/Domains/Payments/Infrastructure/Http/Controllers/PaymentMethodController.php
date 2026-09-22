<?php

declare(strict_types=1);

namespace App\Domains\Payments\Infrastructure\Http\Controllers;

use App\Domains\Payments\Application\UseCases\ListBusinessPaymentMethods;
use App\Domains\Payments\Infrastructure\Http\Resources\BusinessPaymentMethodResource;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

final class PaymentMethodController extends Controller
{
    public function index(
        Request $request,
        ListBusinessPaymentMethods $listBusinessPaymentMethods,
        ApiResponder $responder,
    ): JsonResponse {
        try {
            $response = $listBusinessPaymentMethods->handle();

            if ($response->failed()) {
                return $responder->failure($response->error(), $response->warnings());
            }

            return $responder->success(
                $response,
                BusinessPaymentMethodResource::collection($response->value()),
                Response::HTTP_OK,
            );
        } catch (Throwable $unexpected) {
            return $responder->unexpected($request, $unexpected);
        }
    }
}
