<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Eloquent\Models;

use App\Shared\Infrastructure\Media\BusinessScopedMediaOwner;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

final class MediaModel extends Media
{
    /**
     * @var list<string>
     */
    protected $hidden = ['business_id'];

    protected static function booted(): void
    {
        self::creating(static function (self $media): void {
            if ($media->business_id !== null) {
                return;
            }

            $owner = $media->model;

            if (! $owner instanceof BusinessScopedMediaOwner) {
                return;
            }

            $media->business_id = $owner->businessKey();
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'business_id' => 'integer',
        ];
    }
}
