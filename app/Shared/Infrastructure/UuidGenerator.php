<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure;

use App\Shared\Contracts\IdGenerator;
use Illuminate\Support\Str;

final class UuidGenerator implements IdGenerator
{
    public function next(): string
    {
        return Str::uuid7()->toString();
    }
}
