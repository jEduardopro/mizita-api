<?php

declare(strict_types=1);

namespace App\Domains\Industries\Contracts;

use App\Domains\Industries\Entities\Industry;
use App\Domains\Industries\Exceptions\IndustryNotFound;

/**
 * Port for Industry persistence. It speaks entities, never Eloquent models
 * or query builders, so use cases stay independent of the database.
 *
 * Read only by design: the catalog is seeded, never written over HTTP.
 */
interface IndustryRepository
{
    /**
     * The catalog as a customer sees it: active rows, by position then key.
     *
     * @return list<Industry>
     */
    public function allActive(): array;

    /**
     * Does this row still resolve? True for any catalog row, active or not.
     *
     * A deactivated industry answers true on purpose: a business already
     * pointing at one must keep validating after it leaves the catalog.
     */
    public function existsById(string $id): bool;

    /**
     * May someone choose this today? True only while the row is still offered.
     *
     * The counterpart to existsById, and the difference is the whole point of
     * having both: a retired industry resolves but is not selectable, so
     * onboarding asks this one and anything re-validating an existing choice
     * asks the other.
     */
    public function isSelectable(string $id): bool;

    /**
     * @throws IndustryNotFound
     */
    public function findById(string $id): Industry;
}
