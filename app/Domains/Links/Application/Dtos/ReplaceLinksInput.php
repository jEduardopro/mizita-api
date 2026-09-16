<?php

declare(strict_types=1);

namespace App\Domains\Links\Application\Dtos;

use App\Domains\Links\ValueObjects\LinkOwnerType;
use App\Domains\Links\ValueObjects\LinkTarget;

final readonly class ReplaceLinksInput
{
    /**
     * @param  list<LinkTarget>  $targets
     */
    public function __construct(
        public LinkOwnerType $ownerType,
        public string $ownerId,
        public array $targets,
    ) {}
}
