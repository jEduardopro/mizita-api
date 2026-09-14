<?php

declare(strict_types=1);

namespace App\Http\Exceptions;

use App\Http\Responses\FailurePayload;
use App\Http\Responses\JsonFailureRendering;
use App\Shared\Contracts\DomainFailure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class RenderDomainFailure
{
    public function __invoke(DomainFailure $failure, Request $request): ?JsonResponse
    {
        if (! JsonFailureRendering::appliesTo($request)) {
            return null;
        }

        $payload = FailurePayload::for($failure->errorCode(), $failure->kind());

        return response()->json($payload->body, $payload->status);
    }
}
