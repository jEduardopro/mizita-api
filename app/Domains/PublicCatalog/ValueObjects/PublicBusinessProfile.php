<?php

declare(strict_types=1);

namespace App\Domains\PublicCatalog\ValueObjects;

final readonly class PublicBusinessProfile
{
    public function __construct(
        public string $id,
        public string $name,
        public string $slug,
        public ?string $about,
        public string $timezone,
        public string $currencyCode,
        public ?string $logoUrl,
    ) {}
}
