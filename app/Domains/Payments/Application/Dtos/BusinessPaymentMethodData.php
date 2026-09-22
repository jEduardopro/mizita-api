<?php

declare(strict_types=1);

namespace App\Domains\Payments\Application\Dtos;

use App\Domains\Payments\ValueObjects\AvailablePaymentMethod;

final readonly class BusinessPaymentMethodData
{
    public function __construct(
        public string $id,
        public string $code,
        public int $position,
        public bool $enabled,
        public bool $requiresIntegration,
    ) {}

    public static function fromAvailable(AvailablePaymentMethod $method): self
    {
        return new self(
            id: $method->id,
            code: $method->code,
            position: $method->position,
            enabled: $method->enabled,
            requiresIntegration: $method->requiresIntegration,
        );
    }
}
