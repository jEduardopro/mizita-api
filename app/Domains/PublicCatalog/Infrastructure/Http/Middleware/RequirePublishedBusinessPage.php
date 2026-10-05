<?php

declare(strict_types=1);

namespace App\Domains\PublicCatalog\Infrastructure\Http\Middleware;

use App\Domains\PublicCatalog\Application\Dtos\VerifyBusinessPageInput;
use App\Domains\PublicCatalog\Application\UseCases\VerifyBusinessPage;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class RequirePublishedBusinessPage
{
    private const SLUG_PARAMETER = 'slug';

    public function __construct(
        private readonly VerifyBusinessPage $verifyBusinessPage,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $verification = $this->verifyBusinessPage->handle(
            new VerifyBusinessPageInput((string) $request->route(self::SLUG_PARAMETER)),
        );

        abort_if($verification->failed(), Response::HTTP_NOT_FOUND);

        return $next($request);
    }
}
