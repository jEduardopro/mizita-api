<?php

declare(strict_types=1);

namespace App\Domains\PublicCatalog\Application\Dtos;

final readonly class BusinessPageSharePreview
{
    public function __construct(
        public string $name,
        public ?string $city,
        public ?string $about,
        public ?string $imageUrl,
    ) {}
}
