<?php

declare(strict_types=1);

namespace App\Domains\PublicCatalog\ValueObjects;

final readonly class PublicState
{
    public function __construct(
        public string $id,
        public string $code,
        public string $name,
    ) {}
}
