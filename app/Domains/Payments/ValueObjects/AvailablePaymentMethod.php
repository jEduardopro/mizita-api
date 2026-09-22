<?php

declare(strict_types=1);

namespace App\Domains\Payments\ValueObjects;

final readonly class AvailablePaymentMethod
{
    public function __construct(
        public string $id,
        public string $code,
        public int $position,
        public bool $enabled,
        public bool $requiresIntegration,
    ) {}
}
