<?php

declare(strict_types=1);

namespace App\Domains\Subscriptions\Infrastructure\Http\Controllers;

use App\Domains\Subscriptions\Application\Dtos\OpenBillingPortalInput;
use App\Domains\Subscriptions\Application\UseCases\OpenBillingPortal;
use App\Domains\Subscriptions\Infrastructure\Http\Resources\BillingPortalResource;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponder;
use App\Shared\Contracts\BusinessContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

final class BillingPortalSessionController extends Controller
{
    public function store(
        Request $request,
        OpenBillingPortal $openBillingPortal,
        BusinessContext $business,
        ApiResponder $responder,
    ): JsonResponse {
        try {
            $response = $openBillingPortal->handle(new OpenBillingPortalInput($business->currentBusinessId()));

            if ($response->failed()) {
                return $responder->failure($response->error(), $response->warnings());
            }

            return $responder->success(
                $response,
                BillingPortalResource::make($response->value()),
                Response::HTTP_CREATED,
            );
        } catch (Throwable $unexpected) {
            return $responder->unexpected($request, $unexpected);
        }
    }
}
