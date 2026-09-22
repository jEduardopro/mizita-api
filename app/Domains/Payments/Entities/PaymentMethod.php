<?php

declare(strict_types=1);

namespace App\Domains\Payments\Entities;

final class PaymentMethod
{
    private function __construct(
        public readonly string $id,
        public readonly string $code,
        public readonly int $position,
        public readonly bool $active,
        public readonly bool $requiresIntegration,
    ) {}

    public static function restore(
        string $id,
        string $code,
        int $position,
        bool $active,
        bool $requiresIntegration,
    ): self {
        return new self($id, $code, $position, $active, $requiresIntegration);
    }
}
