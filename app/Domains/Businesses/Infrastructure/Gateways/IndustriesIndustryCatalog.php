<?php

declare(strict_types=1);

namespace App\Domains\Businesses\Infrastructure\Gateways;

use App\Domains\Businesses\Contracts\IndustryCatalog;
use App\Domains\Industries\Contracts\IndustryRepository;

/**
 * Answers this domain's catalog question by asking Industries.
 *
 * Infrastructure is the one layer allowed to know the neighbour exists, so the
 * import stops here: above it there is only IndustryCatalog.
 */
final class IndustriesIndustryCatalog implements IndustryCatalog
{
    public function __construct(
        private readonly IndustryRepository $industries,
    ) {}

    /**
     * isSelectable, not existsById. The neighbour offers both, and onboarding
     * is asking whether someone may choose this industry today - not whether
     * the row still resolves.
     *
     * The weaker question would let a client submit the id of a retired
     * industry it was never shown, since a deactivated row still resolves for
     * the businesses already filed under it.
     */
    public function exists(string $industryId): bool
    {
        return $this->industries->isSelectable($industryId);
    }
}
