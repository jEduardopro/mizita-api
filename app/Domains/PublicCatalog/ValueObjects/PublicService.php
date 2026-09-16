<?php

declare(strict_types=1);

namespace App\Domains\PublicCatalog\ValueObjects;

final readonly class PublicService
{
    public function __construct(
        public string $id,
        public string $name,
        public string $slug,
        public ?string $description,
        public int $durationMinutes,
        public string $price,
        public ?string $imageUrl,
    ) {}
}
