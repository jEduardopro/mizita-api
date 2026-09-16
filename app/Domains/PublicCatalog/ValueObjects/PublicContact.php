<?php

declare(strict_types=1);

namespace App\Domains\PublicCatalog\ValueObjects;

final readonly class PublicContact
{
    /**
     * @param  list<PublicLink>  $links
     */
    public function __construct(
        public ?string $phone,
        public array $links,
    ) {}
}
