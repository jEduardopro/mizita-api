<?php

declare(strict_types=1);

namespace App\Domains\Subscriptions\Infrastructure\Http\Controllers;

use App\Domains\Subscriptions\Application\Dtos\ResumeSubscriptionInput;
use App\Domains\Subscriptions\Application\Dtos\ShowSubscriptionInput;
use App\Domains\Subscriptions\Application\Dtos\SwitchToFreePlanInput;
use App\Domains\Subscriptions\Application\UseCases\ResumeSubscription;
use App\Domains\Subscriptions\Application\UseCases\ShowSubscription;
use App\Domains\Subscriptions\Application\UseCases\SwitchToFreePlan;
use App\Domains\Subscriptions\Infrastructure\Http\Resources\SubscriptionResource;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponder;
use App\Shared\Contracts\BusinessContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

final class SubscriptionController extends Controller
{
    public function show(
        Request $request,
        ShowSubscription $showSubscription,
        BusinessContext $business,
        ApiResponder $responder,
    ): JsonResponse {
        try {
            $response = $showSubscription->handle(new ShowSubscriptionInput($business->currentBusinessId()));

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

    public function switchToFree(
        Request $request,
        SwitchToFreePlan $switchToFreePlan,
        BusinessContext $business,
        ApiResponder $responder,
    ): JsonResponse {
        try {
            $response = $switchToFreePlan->handle(new SwitchToFreePlanInput($business->currentBusinessId()));

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

    public function resume(
        Request $request,
        ResumeSubscription $resumeSubscription,
        BusinessContext $business,
        ApiResponder $responder,
    ): JsonResponse {
        try {
            $response = $resumeSubscription->handle(new ResumeSubscriptionInput($business->currentBusinessId()));

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
