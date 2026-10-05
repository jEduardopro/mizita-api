<?php

declare(strict_types=1);

namespace App\Domains\PublicCatalog\ValueObjects;

use DateTimeImmutable;

final readonly class SitemapBusinessPage
{
    public function __construct(
        public string $slug,
        public ?DateTimeImmutable $lastModified,
    ) {}
}
