<?php

declare(strict_types=1);

namespace App\Shared\Contracts;

/**
 * Produces identifiers for new entities.
 *
 * Generating ids in memory lets an entity be complete before it is persisted,
 * so a use case can reference its id without touching the database.
 */
interface IdGenerator
{
    public function next(): string;
}
