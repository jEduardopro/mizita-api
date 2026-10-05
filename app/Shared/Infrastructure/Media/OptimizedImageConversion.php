<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Media;

use Spatie\Image\Enums\Fit;
use Spatie\MediaLibrary\HasMedia;

final class OptimizedImageConversion
{
    public const NAME = 'optimized';

    private const FORMAT = 'webp';

    private const QUALITY = 80;

    public static function register(HasMedia $owner, int $maximumDimension, string ...$collections): void
    {
        $owner->addMediaConversion(self::NAME)
            ->format(self::FORMAT)
            ->quality(self::QUALITY)
            ->fit(Fit::Max, $maximumDimension, $maximumDimension)
            ->nonOptimized()
            ->nonQueued()
            ->performOnCollections(...$collections);
    }
}
