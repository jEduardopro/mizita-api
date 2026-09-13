<?php

declare(strict_types=1);

namespace App\Domains\Businesses\Contracts;

use App\Domains\Businesses\Entities\Business;
use App\Domains\Businesses\Exceptions\BusinessNameAlreadyTaken;
use App\Domains\Businesses\Exceptions\BusinessNotFound;
use App\Domains\Businesses\Exceptions\BusinessSlugAlreadyTaken;

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

    /**
     * Whether any live business trades under this name, compared case
     * insensitively: "Barbería López" and "barbería lópez" are one name to a
     * customer, so they have to be one name here too.
     */
    public function existsByName(string $name): bool;

    /**
     * The live slugs that could collide with a base: the base itself, and the
     * numbered variants of it. One read, so the allocator can pick a free
     * suffix without a query per attempt.
     *
     * @return list<string>
     */
    public function slugsMatching(string $base): array;

    /**
     * @throws BusinessNameAlreadyTaken when another business won the name
     * @throws BusinessSlugAlreadyTaken when another business won the address
     */
    public function save(Business $business): void;

    /**
     * Soft deletes the record. Reads stop returning it; the row is kept.
     *
     * @throws BusinessNotFound
     */
    public function delete(string $id): void;
}
