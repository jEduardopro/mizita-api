<?php

declare(strict_types=1);

namespace App\Domains\PublicCatalog\ValueObjects;

final readonly class PublicLink
{
    public function __construct(
        public string $platform,
        public string $url,
    ) {}
}
