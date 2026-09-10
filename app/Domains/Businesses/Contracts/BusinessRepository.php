<?php

declare(strict_types=1);

namespace App\Domains\Businesses\Contracts;

use App\Domains\Businesses\Entities\Business;
use App\Domains\Businesses\Exceptions\BusinessNotFound;

/**
 * Port for Business persistence. It speaks entities, never Eloquent models
 * or query builders, so use cases stay independent of the database.
 */
interface BusinessRepository
{
    /**
     * @throws BusinessNotFound
     */
    public function findById(string $id): Business;

    public function existsBySlug(string $slug): bool;

    public function save(Business $business): void;

    /**
     * Soft deletes the record. Reads stop returning it; the row is kept.
     *
     * @throws BusinessNotFound
     */
    public function delete(string $id): void;
}
