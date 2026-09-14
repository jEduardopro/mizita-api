<?php

declare(strict_types=1);

namespace App\Domains\Industries\Contracts;

use App\Domains\Industries\Entities\Industry;
use App\Domains\Industries\Exceptions\IndustryNotFound;

/**
 * Read only by design: the catalog is seeded, never written over HTTP.
 */
interface IndustryRepository
{
    /**
     * Active rows, by position then key.
     *
     * @return list<Industry>
     */
    public function allActive(): array;

    /**
     * True for any catalog row, active or not: a business already pointing at a
     * deactivated industry must keep validating after it leaves the catalog.
     */
    public function existsById(string $id): bool;

    /** True only while the row is still offered, which is what onboarding asks. */
    public function isSelectable(string $id): bool;

    /**
     * @throws IndustryNotFound
     */
    public function findById(string $id): Industry;
}
