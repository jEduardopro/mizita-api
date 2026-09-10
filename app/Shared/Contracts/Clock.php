<?php

declare(strict_types=1);

namespace App\Shared\Contracts;

use DateTimeImmutable;

/**
 * Supplies the current time to use cases and entities.
 *
 * Injecting time instead of calling now() keeps use cases deterministic:
 * a test can freeze the clock and assert exact timestamps.
 */
interface Clock
{
    public function now(): DateTimeImmutable;
}
