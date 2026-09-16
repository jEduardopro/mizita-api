<?php

declare(strict_types=1);

namespace App\Domains\PublicCatalog\Application\Dtos;

final readonly class ShowPublicBusinessPageInput
{
    public function __construct(
        public string $slug,
    ) {}
}
