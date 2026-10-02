<?php

declare(strict_types=1);

namespace App\Domains\Platform\ValueObjects;

final readonly class PlatformBusinessOwner
{
    public function __construct(
        public string $name,
        public string $email,
    ) {}
}
