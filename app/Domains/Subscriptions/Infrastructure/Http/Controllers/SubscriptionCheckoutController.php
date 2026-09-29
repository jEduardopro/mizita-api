<?php

declare(strict_types=1);

namespace App\Domains\Subscriptions\Infrastructure\Http\Controllers;

use App\Domains\Subscriptions\Application\Dtos\ConfirmCheckoutInput;
use App\Domains\Subscriptions\Application\Dtos\StartCheckoutInput;
use App\Domains\Subscriptions\Application\UseCases\ConfirmCheckout;
use App\Domains\Subscriptions\Application\UseCases\StartCheckout;
use App\Domains\Subscriptions\Infrastructure\Http\Requests\StartCheckoutRequest;
use App\Domains\Subscriptions\Infrastructure\Http\Resources\CheckoutSessionResource;
use App\Domains\Subscriptions\Infrastructure\Http\Resources\SubscriptionResource;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponder;
use App\Shared\Contracts\BusinessContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

final class SubscriptionCheckoutController extends Controller
{
    public function store(
        StartCheckoutRequest $request,
        StartCheckout $startCheckout,
        BusinessContext $business,
        ApiResponder $responder,
    ): JsonResponse {
        try {
            $response = $startCheckout->handle(
                StartCheckoutInput::fromRequest($request->validated(), $business->currentBusinessId()),
            );

            if ($response->failed()) {
                return $responder->failure($response->error(), $response->warnings());
            }

            return $responder->success(
                $response,
                CheckoutSessionResource::make($response->value()),
                Response::HTTP_CREATED,
            );
        } catch (Throwable $unexpected) {
            return $responder->unexpected($request, $unexpected);
        }
    }

    public function confirm(
        Request $request,
        string $session,
        ConfirmCheckout $confirmCheckout,
        BusinessContext $business,
        ApiResponder $responder,
    ): JsonResponse {
        try {
            $response = $confirmCheckout->handle(new ConfirmCheckoutInput($business->currentBusinessId(), $session));

            if ($response->failed()) {
                return $responder->failure($response->error(), $response->warnings());
            }

            return $responder->success(
                $response,
                SubscriptionResource::make($response->value()),
                Response::HTTP_OK,
            );
        } catch (Throwable $unexpected) {
            return $responder->unexpected($request, $unexpected);
        }
    }
}
