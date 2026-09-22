<?php

declare(strict_types=1);

namespace App\Domains\Payments\Application\Dtos;

use App\Domains\Payments\Entities\PaymentItem;

final readonly class PaymentItemData
{
    public function __construct(
        public string $id,
        public string $name,
        public int $amountCents,
        public int $position,
    ) {}

    public static function fromItem(PaymentItem $item): self
    {
        return new self(
            id: $item->id,
            name: $item->name->value,
            amountCents: $item->amount->amount,
            position: $item->position,
        );
    }
}
