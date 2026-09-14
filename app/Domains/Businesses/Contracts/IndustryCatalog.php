<?php

declare(strict_types=1);

namespace App\Domains\Businesses\Contracts;

/**
 * Declared here rather than imported from Industries, because the consumer owns
 * the shape of what it consumes: Businesses never sees an Industry entity, so
 * the catalog can grow labels and ordering without a file here changing.
 */
interface IndustryCatalog
{
    /**
     * "Exists" as onboarding means it: a retired industry does not exist to
     * somebody choosing one, even though its row is still there for the
     * businesses already using it.
     */
    public function exists(string $industryId): bool;
}
