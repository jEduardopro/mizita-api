<?php

declare(strict_types=1);

namespace App\Domains\PublicCatalog\Application\Dtos;

use App\Domains\PublicCatalog\Exceptions\BusinessPageNotFound;
use App\Domains\PublicCatalog\ValueObjects\BusinessPageSlug;

final readonly class DescribeBusinessPageSharePreviewInput
{
    public function __construct(
        public string $slug,
    ) {}

    /**
     * @throws BusinessPageNotFound
     */
    public function validate(): void
    {
        BusinessPageSlug::fromString($this->slug);
    }
}
