<?php

declare(strict_types=1);

namespace App\Domains\Links\ValueObjects;

final readonly class LinkTarget
{
    public function __construct(
        public LinkPlatform $platform,
        public LinkUrl $url,
    ) {}
}
