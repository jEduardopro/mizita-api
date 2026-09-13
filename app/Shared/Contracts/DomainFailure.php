<?php

declare(strict_types=1);

namespace App\Shared\Contracts;

use App\Shared\ValueObjects\DomainFailureKind;

/**
 * A failure a domain raises on purpose, carrying enough information to be
 * reported to the caller without anybody having to recognise its class.
 *
 * Implemented by domain exceptions. The alternative - a central match over
 * exception class names at the HTTP edge - puts every domain's vocabulary in
 * one file that has to be edited from the outside every time a domain grows a
 * new rule. Here the exception classifies itself and the renderer stays fixed.
 */
interface DomainFailure
{
    /**
     * The stable machine code a client can branch on, snake_case.
     *
     * It doubles as the translation key under messages.errors, so a new failure
     * is one exception plus one line in each locale catalogue.
     */
    public function errorCode(): string;

    public function kind(): DomainFailureKind;
}
