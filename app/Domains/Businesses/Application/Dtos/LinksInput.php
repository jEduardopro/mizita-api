<?php

declare(strict_types=1);

namespace App\Domains\Businesses\Application\Dtos;

use App\Domains\Businesses\ValueObjects\BusinessLinkSnapshot;

final readonly class LinksInput
{
    /**
     * @param  list<BusinessLinkSnapshot>  $links
     */
    public function __construct(
        public array $links,
    ) {}
}
