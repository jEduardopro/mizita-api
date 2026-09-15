<?php

declare(strict_types=1);

namespace App\Domains\Services\ValueObjects;

final readonly class StaffMemberSnapshot
{
    public function __construct(
        public string $id,
        public string $name,
    ) {}
}
