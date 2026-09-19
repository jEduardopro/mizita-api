<?php

declare(strict_types=1);

namespace App\Domains\PublicCatalog\Application\Dtos;

use App\Domains\PublicCatalog\Exceptions\BusinessPageNotFound;
use App\Domains\PublicCatalog\ValueObjects\BusinessPageSlug;
use App\Domains\PublicCatalog\ValueObjects\PublicBookingCredentials;

final readonly class CancelPublicBookingInput
{
    public function __construct(
        public string $slug,
        public PublicBookingCredentials $credentials,
    ) {}

    /**
     * @throws BusinessPageNotFound
     */
    public function validate(): void
    {
        $this->validateSlug();
    }

    private function validateSlug(): void
    {
        BusinessPageSlug::fromString($this->slug);
    }
}
