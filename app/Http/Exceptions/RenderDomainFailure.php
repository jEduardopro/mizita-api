<?php

declare(strict_types=1);

namespace App\Http\Exceptions;

use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Turns any domain failure into its API error body.
 *
 * One renderer for every domain: the exception carries its own code and kind,
 * so this class never grows a branch per exception class and a new domain
 * needs no change here at all.
 */
final class RenderDomainFailure
{
    /**
     * Returns null when this request must not be answered with JSON, which
     * hands the failure back to the default handler and leaves the web stack's
     * redirect-with-errors behaviour intact.
     */
    public function __invoke(DomainFailure $failure, Request $request): ?JsonResponse
    {
        if (! $this->wantsJson($request)) {
            return null;
        }

        return response()->json([
            // The exception's own getMessage() is never sent. Those are English
            // developer strings written for a stack trace, and several of them
            // interpolate the identifier that caused the failure - a slug, an
            // email - which is exactly what an error body must not confirm.
            'message' => __('messages.errors.'.$failure->errorCode()),
            'code' => $failure->errorCode(),
        ], $this->statusFor($failure->kind()));
    }

    /**
     * Mirrors the predicate in bootstrap/app.php's shouldRenderJsonWhen: an
     * Inertia visit negotiates HTML and must keep getting it, even when the
     * client also declares it accepts JSON.
     */
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
