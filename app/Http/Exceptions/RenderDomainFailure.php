<?php

declare(strict_types=1);

namespace App\Http\Exceptions;

use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class RenderDomainFailure
{
    public function __invoke(DomainFailure $failure, Request $request): ?JsonResponse
    {
        if (! $this->wantsJson($request)) {
            return null;
        }

        return response()->json([
            'message' => __('messages.errors.'.$failure->errorCode()),
            'code' => $failure->errorCode(),
        ], $this->statusFor($failure->kind()));
    }

    private function wantsJson(Request $request): bool
    {
        return ! $request->hasHeader('X-Inertia')
            && ($request->is('api/*') || $request->expectsJson());
    }

    private function statusFor(DomainFailureKind $kind): int
    {
        return match ($kind) {
            DomainFailureKind::Invalid => Response::HTTP_UNPROCESSABLE_ENTITY,
            DomainFailureKind::Conflict => Response::HTTP_CONFLICT,
            DomainFailureKind::NotFound => Response::HTTP_NOT_FOUND,
            DomainFailureKind::Unauthenticated => Response::HTTP_UNAUTHORIZED,
            DomainFailureKind::Forbidden => Response::HTTP_FORBIDDEN,
        };
    }
}
