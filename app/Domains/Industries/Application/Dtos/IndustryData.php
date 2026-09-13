<?php

declare(strict_types=1);

namespace App\Domains\Industries\Application\Dtos;

use App\Domains\Industries\Entities\Industry;

/**
 * Output boundary. Entities never leave the application layer, so use cases
 * return this instead.
 *
 * Carries only what a catalog consumer needs: the active flag would be true
 * for every row a caller can ever see, and timestamps say nothing about an
 * option in a list.
 */
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
