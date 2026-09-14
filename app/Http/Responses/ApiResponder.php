<?php

declare(strict_types=1);

namespace App\Http\Responses;

use App\Http\Logging\FailureLogContext;
use App\Shared\Application\UseCaseError;
use App\Shared\Application\UseCaseResponse;
use App\Shared\Application\Warning;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Psr\Log\LoggerInterface;
use Throwable;

final readonly class ApiResponder
{
    public function __construct(
        private LoggerInterface $logger,
    ) {}

    /**
     * @param  UseCaseResponse<mixed>  $response
     */
    public function success(UseCaseResponse $response, JsonResource $resource, int $status): JsonResponse
    {
        return $resource
            ->additional(WarningEnvelope::for($response->warnings()))
            ->response()
            ->setStatusCode($status);
    }

    /**
     * @param  list<Warning>  $warnings
     */
    public function failure(UseCaseError $error, array $warnings = []): JsonResponse
    {
        $payload = FailurePayload::for($error->code, $error->kind);

        return response()->json(
            [...$payload->body, ...WarningEnvelope::for($warnings)],
            $payload->status,
        );
    }

    public function unexpected(Request $request, Throwable $error): JsonResponse
    {
        $this->logger->error($error->getMessage(), FailureLogContext::for($request, $error));

        $payload = FailurePayload::serverError();

        return response()->json($payload->body, $payload->status);
    }
}
