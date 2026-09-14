<?php

declare(strict_types=1);

namespace App\Http\Responses;

use App\Http\Logging\FailureLogContext;
use App\Shared\Application\UseCaseError;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Psr\Log\LoggerInterface;
use Throwable;

final readonly class WebResponder
{
    public function __construct(
        private LoggerInterface $logger,
    ) {}

    public function backTo(string $route, string $errorKey, UseCaseError $error): RedirectResponse
    {
        return $this->backToWithErrorCode($route, $errorKey, $error->code);
    }

    public function backToWithErrorCode(string $route, string $errorKey, string $errorCode): RedirectResponse
    {
        return $this->redirectWith($route, $errorKey, FailurePayload::messageFor($errorCode));
    }

    public function unexpected(Request $request, Throwable $error, string $route, string $errorKey): RedirectResponse
    {
        $this->logger->error($error->getMessage(), FailureLogContext::for($request, $error));

        return $this->redirectWith($route, $errorKey, FailurePayload::serverError()->message);
    }

    private function redirectWith(string $route, string $errorKey, string $message): RedirectResponse
    {
        return redirect()->route($route)
            ->withErrors([$errorKey => $message])
            ->with('error', $message);
    }
}
