<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure;

use App\Shared\Contracts\BusinessContext;

/**
 * Immutable holder bound into the container per request by the
 * SetBusinessContext middleware.
 */
final readonly class RequestBusinessContext implements BusinessContext
{
    public function __construct(
        private string $businessId,
    ) {}

    public function currentBusinessId(): string
    {
        return $this->businessId;
    }
}
