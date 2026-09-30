<?php

declare(strict_types=1);

namespace App\Domains\Statistics\ValueObjects;

final readonly class PaymentMethodCollection
{
    public function __construct(
        public string $code,
        public int $collectedCents,
    ) {}
}
