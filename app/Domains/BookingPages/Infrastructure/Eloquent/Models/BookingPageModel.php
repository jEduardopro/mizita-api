<?php

declare(strict_types=1);

namespace App\Domains\BookingPages\Infrastructure\Eloquent\Models;

use App\Domains\BookingPages\Infrastructure\Eloquent\Factories\BookingPageModelFactory;
use App\Domains\BookingPages\ValueObjects\BrandColor;
use App\Domains\BookingPages\ValueObjects\ButtonShape;
use App\Domains\BookingPages\ValueObjects\PageTheme;
use App\Domains\Businesses\Infrastructure\Eloquent\Models\BusinessModel;
use App\Shared\Infrastructure\Media\BusinessScopedMediaOwner;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

#[Fillable(['uuid', 'business_id', 'accent_color', 'button_shape', 'theme'])]
class BookingPageModel extends Model implements BusinessScopedMediaOwner, HasMedia
{
    use HasFactory;
    use HasUuids;
    use InteractsWithMedia;
    use SoftDeletes;

    public const BANNER_COLLECTION = 'banner';

    public const GALLERY_COLLECTION = 'gallery';

    /**
     * @var list<string>
     */
    public const ACCEPTED_IMAGE_MIME_TYPES = [
        'image/jpeg',
        'image/png',
        'image/webp',
    ];

    protected $table = 'booking_pages';

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection(self::BANNER_COLLECTION)
            ->singleFile()
            ->acceptsMimeTypes(self::ACCEPTED_IMAGE_MIME_TYPES);

        $this->addMediaCollection(self::GALLERY_COLLECTION)
            ->acceptsMimeTypes(self::ACCEPTED_IMAGE_MIME_TYPES);
    }

    /**
     * @return array<int, string>
     */
    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function businessKey(): int
    {
        return (int) $this->business_id;
    }

    /**
     * @return BelongsTo<BusinessModel, $this>
     */
    public function business(): BelongsTo
    {
        return $this->belongsTo(BusinessModel::class, 'business_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'accent_color' => BrandColor::class,
            'button_shape' => ButtonShape::class,
            'theme' => PageTheme::class,
        ];
    }

    protected static function newFactory(): BookingPageModelFactory
    {
        return BookingPageModelFactory::new();
    }
}
