<?php

declare(strict_types=1);

namespace App\Http\Seo;

final readonly class SeoMeta
{
    public function __construct(
        public string $title,
        public string $shareTitle,
        public string $description,
        public string $canonicalUrl,
        public RobotsDirective $robots,
        public ?string $imageUrl,
    ) {}
}
