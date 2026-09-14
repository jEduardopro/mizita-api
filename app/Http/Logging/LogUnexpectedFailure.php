<?php

declare(strict_types=1);

namespace App\Http\Logging;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\Request;
use Psr\Log\LoggerInterface;
use Throwable;

final readonly class LogUnexpectedFailure
{
    public function __construct(
        private LoggerInterface $logger,
        private Application $app,
    ) {}

    public function __invoke(Throwable $error): void
    {
        $this->logger->error($error->getMessage(), $this->contextFor($error));
    }

    /**
     * @return array{exception: Throwable, route?: string|null, method?: string, path?: string, user_id?: string|null}
     */
    private function contextFor(Throwable $error): array
    {
        if ($this->app->runningInConsole()) {
            return FailureLogContext::withoutRequest($error);
        }

        return FailureLogContext::for($this->app->make(Request::class), $error);
    }
}
