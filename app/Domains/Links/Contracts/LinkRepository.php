<?php

declare(strict_types=1);

namespace App\Domains\Links\Contracts;

use App\Domains\Links\Entities\Link;
use App\Domains\Links\ValueObjects\LinkOwnerType;

interface LinkRepository
{
    /**
     * @return list<Link>
     */
    public function allForOwner(LinkOwnerType $ownerType, string $ownerId): array;

    public function save(Link $link): void;

    /**
     * @param  list<Link>  $links
     */
    public function replaceForOwner(LinkOwnerType $ownerType, string $ownerId, array $links): void;

    public function deleteForOwner(LinkOwnerType $ownerType, string $ownerId): void;
}
