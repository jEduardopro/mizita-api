<?php

declare(strict_types=1);

namespace App\Domains\Payments\Infrastructure\Http\Controllers;

use App\Domains\Payments\Application\Dtos\RecordPaymentTransactionInput;
use App\Domains\Payments\Application\Dtos\VoidPaymentTransactionInput;
use App\Domains\Payments\Application\UseCases\RecordPaymentTransaction;
use App\Domains\Payments\Application\UseCases\VoidPaymentTransaction;
use App\Domains\Payments\Infrastructure\Http\Requests\RecordPaymentTransactionRequest;
use App\Domains\Payments\Infrastructure\Http\Resources\PaymentResource;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponder;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

final class PaymentTransactionController extends Controller
{
    public function store(
        RecordPaymentTransactionRequest $request,
        string $payment,
        RecordPaymentTransaction $recordPaymentTransaction,
        ApiResponder $responder,
    ): JsonResponse {
        /** @var User $actor */
        $actor = $request->user();

        try {
            $response = $recordPaymentTransaction->handle(
                RecordPaymentTransactionInput::fromRequest($request->validated(), $payment, $actor->uuid),
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

    public function void(
        Request $request,
        string $payment,
        string $transaction,
        VoidPaymentTransaction $voidPaymentTransaction,
        ApiResponder $responder,
    ): JsonResponse {
        /** @var User $actor */
        $actor = $request->user();

        try {
            $response = $voidPaymentTransaction->handle(
                new VoidPaymentTransactionInput($payment, $transaction, $actor->uuid),
            );

            if ($response->failed()) {
                return $responder->failure($response->error(), $response->warnings());
            }

            return $responder->success(
                $response,
                PaymentResource::make($response->value()),
                Response::HTTP_OK,
            );
        } catch (Throwable $unexpected) {
            return $responder->unexpected($request, $unexpected);
        }
    }
}
