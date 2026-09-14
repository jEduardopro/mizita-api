<?php

declare(strict_types=1);

namespace App\Shared\Application;

final readonly class Warning
{
    public function __construct(
        public string $code,
    ) {}
}
