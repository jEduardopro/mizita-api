<?php

declare(strict_types=1);

namespace Tests\Support\Media;

use App\Shared\Infrastructure\Media\OptimizedImageConversion;
use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

final class OptimizedImageOwnerModel extends Model implements HasMedia
{
    use InteractsWithMedia;

    public const PHOTO_COLLECTION = 'photos';

    public const COVER_COLLECTION = 'covers';

    public const MAXIMUM_DIMENSION = 640;

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection(self::PHOTO_COLLECTION);
        $this->addMediaCollection(self::COVER_COLLECTION);
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        OptimizedImageConversion::register($this, self::MAXIMUM_DIMENSION, self::PHOTO_COLLECTION, self::COVER_COLLECTION);
    }
}
