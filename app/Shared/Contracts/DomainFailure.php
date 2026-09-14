<?php

declare(strict_types=1);

namespace App\Shared\Contracts;

use App\Shared\ValueObjects\DomainFailureKind;

/**
 * Lets an exception classify itself, so the renderer stays fixed. The
 * alternative - a central match over class names at the HTTP edge - puts every
 * domain's vocabulary in one file edited from outside every time a rule lands.
 */
interface DomainFailure
{
    /** Snake_case, and doubles as the translation key under messages.errors. */
    public function errorCode(): string;

    public function kind(): DomainFailureKind;
}
