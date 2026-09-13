<?php

declare(strict_types=1);

namespace App\Domains\Businesses\Contracts;

/**
 * What this domain needs to know about the industry catalog, which is all of
 * one thing: whether the industry a signup chose is real.
 *
 * Declared here rather than imported from Industries, because the consumer owns
 * the shape of what it consumes. Businesses never sees an Industry entity, so
 * the catalog can grow labels, ordering and translations without a single file
 * here changing.
 */
interface IndustryCatalog
{
    /**
     * Whether this industry is one a business may be filed under right now.
     *
     * "Exists" is meant as onboarding means it: an industry that has been
     * retired from the catalog does not exist to somebody choosing one, even
     * though its row is still there for the businesses already using it.
     */
    public function exists(string $industryId): bool;
}
