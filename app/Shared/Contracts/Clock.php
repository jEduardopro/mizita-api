<?php

declare(strict_types=1);

namespace App\Shared\Contracts;

use DateTimeImmutable;

/** Injected instead of now(), so a test can freeze time and assert exact timestamps. */
interface Clock
{
    public function now(): DateTimeImmutable;
}
