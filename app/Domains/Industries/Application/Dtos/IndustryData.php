<?php

declare(strict_types=1);

namespace App\Domains\Industries\Application\Dtos;

use App\Domains\Industries\Entities\Industry;

final readonly class IndustryData
{
    public function __construct(
        public string $id,
        public string $key,
        public int $position,
    ) {}

    public static function fromEntity(Industry $industry): self
    {
        return new self(
            id: $industry->id,
            key: $industry->key(),
            position: $industry->position(),
        );
    }
}
