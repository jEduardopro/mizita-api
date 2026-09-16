<?php

declare(strict_types=1);

namespace App\Domains\PublicCatalog\ValueObjects;

final readonly class PublicGalleryImage
{
    public function __construct(
        public string $id,
        public string $url,
    ) {}
}
