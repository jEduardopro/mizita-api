<?php

declare(strict_types=1);

namespace App\Domains\PublicCatalog\ValueObjects;

final readonly class PublicBrand
{
    /**
     * @param  list<PublicGalleryImage>  $gallery
     */
    public function __construct(
        public string $accentColor,
        public string $buttonShape,
        public string $theme,
        public ?string $bannerUrl,
        public array $gallery,
    ) {}
}
