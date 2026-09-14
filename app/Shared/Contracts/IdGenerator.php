<?php

declare(strict_types=1);

namespace App\Shared\Contracts;

/** Generated in memory, so an entity is complete - and referenceable - before it is persisted. */
interface IdGenerator
{
    public function next(): string;
}
