<?php

declare(strict_types=1);

namespace App\Domains\Links\Application\Dtos;

use App\Domains\Links\Entities\Link;

final readonly class LinkData
{
    public function __construct(
        public string $id,
        public string $platform,
        public string $url,
        public int $position,
    ) {}

    public static function fromEntity(Link $link): self
    {
        return new self(
            id: $link->id,
            platform: $link->platform->value,
            url: $link->url()->value,
            position: $link->position(),
        );
    }
}
