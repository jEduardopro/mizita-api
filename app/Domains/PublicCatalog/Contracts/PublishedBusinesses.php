<?php

declare(strict_types=1);

namespace App\Domains\PublicCatalog\Contracts;

use App\Domains\PublicCatalog\Exceptions\BusinessPageNotFound;
use App\Domains\PublicCatalog\ValueObjects\PublicBusinessProfile;

interface PublishedBusinesses
{
    /**
     * @throws BusinessPageNotFound
     */
    public function findBySlug(string $slug): PublicBusinessProfile;

    public function existsBySlug(string $slug): bool;
}
