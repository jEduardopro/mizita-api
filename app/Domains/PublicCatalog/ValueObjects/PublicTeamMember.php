<?php

declare(strict_types=1);

namespace App\Domains\PublicCatalog\ValueObjects;

final readonly class PublicTeamMember
{
    public function __construct(
        public string $id,
        public string $name,
    ) {}
}
