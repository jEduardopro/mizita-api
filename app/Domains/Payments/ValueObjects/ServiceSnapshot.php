<?php

declare(strict_types=1);

namespace App\Domains\Payments\ValueObjects;

final readonly class ServiceSnapshot
{
    public function __construct(
        public string $id,
        public string $name,
        public Money $price,
    ) {}
}
