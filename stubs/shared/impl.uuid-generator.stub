<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure;

use App\Shared\Contracts\IdGenerator;
use Illuminate\Support\Str;

final class UuidGenerator implements IdGenerator
{
    /**
     * UUID v7 is time ordered, which keeps the unique index on the uuid
     * column from fragmenting the way random v4 values do.
     */
    public function next(): string
    {
        return Str::uuid7()->toString();
    }
}
