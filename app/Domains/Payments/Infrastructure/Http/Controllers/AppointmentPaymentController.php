<?php

declare(strict_types=1);

namespace App\Domains\Payments\Infrastructure\Http\Controllers;

use App\Domains\Payments\Application\Dtos\CreateAppointmentPaymentInput;
use App\Domains\Payments\Application\Dtos\ShowAppointmentPaymentInput;
use App\Domains\Payments\Application\UseCases\CreateAppointmentPayment;
use App\Domains\Payments\Application\UseCases\ShowAppointmentPayment;
use App\Domains\Payments\Infrastructure\Http\Requests\CreateAppointmentPaymentRequest;
use App\Domains\Payments\Infrastructure\Http\Resources\PaymentResource;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponder;
use App\Http\Responses\NullResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

final class AppointmentPaymentController extends Controller
{
    public function show(
        Request $request,
        string $appointment,
        ShowAppointmentPayment $showAppointmentPayment,
        ApiResponder $responder,
    ): JsonResponse {
        /** @var User $actor */
        $actor = $request->user();

        try {
            $response = $showAppointmentPayment->handle(new ShowAppointmentPaymentInput($appointment, $actor->uuid));

            if ($response->failed()) {
                return $responder->failure($response->error(), $response->warnings());
            }

            $payment = $response->value();

            return $responder->success(
                $response,
                $payment === null ? NullResource::instance() : PaymentResource::make($payment),
                Response::HTTP_OK,
            );
        } catch (Throwable $unexpected) {
            return $responder->unexpected($request, $unexpected);
        }
    }

    public function store(
        CreateAppointmentPaymentRequest $request,
        string $appointment,
        CreateAppointmentPayment $createAppointmentPayment,
        ApiResponder $responder,
    ): JsonResponse {
        /** @var User $actor */
        $actor = $request->user();

        try {
            $response = $createAppointmentPayment->handle(
                CreateAppointmentPaymentInput::fromRequest($request->validated(), $appointment, $actor->uuid),
            );

            if ($response->failed()) {
                return $responder->failure($response->error(), $response->warnings());
            }

            return $responder->success(
                $response,
                PaymentResource::make($response->value()),
                Response::HTTP_CREATED,
            );
        } catch (Throwable $unexpected) {
            return $responder->unexpected($request, $unexpected);
        }
    }
}
