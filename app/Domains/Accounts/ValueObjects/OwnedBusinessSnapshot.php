<?php

declare(strict_types=1);

namespace App\Domains\Accounts\ValueObjects;

final readonly class OwnedBusinessSnapshot
{
    public function __construct(
        public string $id,
        public string $name,
    ) {}
}
