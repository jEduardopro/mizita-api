<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Media;

use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

final class OptimizedImageUrl
{
    public static function of(Media $media): string
    {
        return $media->getAvailableUrl([OptimizedImageConversion::NAME]);
    }

    public static function firstOf(HasMedia $owner, string $collection): ?string
    {
        $media = $owner->getMedia($collection)->first();

        return $media instanceof Media ? self::of($media) : null;
    }
}
