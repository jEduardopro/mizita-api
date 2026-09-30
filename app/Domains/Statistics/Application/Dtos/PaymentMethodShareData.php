<?php

declare(strict_types=1);

namespace App\Domains\Statistics\Application\Dtos;

final readonly class PaymentMethodShareData
{
    public function __construct(
        public string $code,
        public int $collectedCents,
        public float $sharePercent,
    ) {}
}
