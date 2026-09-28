<?php

declare(strict_types=1);

namespace App\Domains\Subscriptions\Infrastructure\Console\Concerns;

use App\Shared\Application\UseCaseError;

trait ReportsFailedUseCase
{
    private const ERROR_MESSAGES = 'messages.errors.';

    private function reportFailure(UseCaseError $error): int
    {
        $this->error(sprintf('%s [%s]', __(self::ERROR_MESSAGES.$error->code), $error->code));

        return self::FAILURE;
    }
}
