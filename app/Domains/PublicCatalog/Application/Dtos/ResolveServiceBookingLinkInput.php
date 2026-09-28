<?php

declare(strict_types=1);

namespace App\Domains\PublicCatalog\Application\Dtos;

use App\Domains\PublicCatalog\Exceptions\BusinessPageNotFound;
use App\Domains\PublicCatalog\ValueObjects\BusinessPageSlug;

final readonly class ResolveServiceBookingLinkInput
{
    public function __construct(
        public string $businessSlug,
        public string $serviceSlug,
    ) {}

    /**
     * @throws BusinessPageNotFound
     */
    public function validate(): void
    {
        $this->validateBusinessSlug();
    }

    private function validateBusinessSlug(): void
    {
        BusinessPageSlug::fromString($this->businessSlug);
    }
}
