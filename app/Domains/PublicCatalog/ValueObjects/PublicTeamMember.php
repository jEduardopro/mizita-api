<?php

declare(strict_types=1);

namespace App\Domains\PublicCatalog\ValueObjects;

final readonly class PublicTeamMember
{
    public function __construct(
        public string $id,
        public string $name,
        public ?string $photoUrl,
        public ?string $jobTitle,
        public ?string $about,
        public ?string $bookingUrl,
    ) {}
}
