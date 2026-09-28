<?php

declare(strict_types=1);

namespace App\Domains\PublicCatalog\Application\Dtos;

use App\Domains\PublicCatalog\Exceptions\BusinessPageNotFound;
use App\Domains\PublicCatalog\Exceptions\StaffBookingPageNotFound;
use App\Domains\PublicCatalog\ValueObjects\BusinessPageSlug;
use App\Domains\PublicCatalog\ValueObjects\StaffPageSlug;

final readonly class ResolveStaffBookingLinkInput
{
    public function __construct(
        public string $businessSlug,
        public string $staffSlug,
        public ?string $serviceSlug,
    ) {}

    /**
     * @throws BusinessPageNotFound
     * @throws StaffBookingPageNotFound
     */
    public function validate(): void
    {
        $this->validateBusinessSlug();
        $this->validateStaffSlug();
    }

    private function validateBusinessSlug(): void
    {
        BusinessPageSlug::fromString($this->businessSlug);
    }

    private function validateStaffSlug(): void
    {
        StaffPageSlug::fromString($this->staffSlug);
    }
}
