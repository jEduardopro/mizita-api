<?php

declare(strict_types=1);

namespace Tests\Support\Architecture;

use DomainException;

final class ArityFixtureFailure extends DomainException
{
    public static function withOne(int $cents): self
    {
        return new self("[{$cents}] is not a valid amount.");
    }

    public static function withBounds(int $cents, int $maximum): self
    {
        return new self("[{$cents}] exceeds the maximum of {$maximum}.");
    }

    public static function withOptional(int $cents, ?int $maximum = null): self
    {
        return new self("[{$cents}] exceeds the maximum of {$maximum}.");
    }
}
