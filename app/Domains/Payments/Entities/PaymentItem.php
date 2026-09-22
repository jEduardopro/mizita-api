<?php

declare(strict_types=1);

namespace App\Domains\Payments\Entities;

use App\Domains\Payments\ValueObjects\Money;
use App\Domains\Payments\ValueObjects\PaymentItemName;

final class PaymentItem
{
    private function __construct(
        public readonly string $id,
        public readonly PaymentItemName $name,
        public readonly Money $amount,
        public readonly int $position,
    ) {}

    public static function create(string $id, PaymentItemName $name, Money $amount, int $position): self
    {
        return new self($id, $name, $amount, $position);
    }

    public static function restore(string $id, PaymentItemName $name, Money $amount, int $position): self
    {
        return new self($id, $name, $amount, $position);
    }
}
