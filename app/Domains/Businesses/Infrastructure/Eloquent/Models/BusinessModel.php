<?php

declare(strict_types=1);

namespace App\Domains\Businesses\Infrastructure\Eloquent\Models;

use App\Domains\Businesses\Infrastructure\Eloquent\Factories\BusinessModelFactory;
use App\Domains\Industries\Infrastructure\Eloquent\Models\IndustryModel;
use App\Shared\Infrastructure\Media\BusinessScopedMediaOwner;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

#[Fillable(['uuid', 'name', 'slug', 'industry_id', 'timezone', 'contact_email', 'about', 'currency_code'])]
class BusinessModel extends Model implements BusinessScopedMediaOwner, HasMedia
{
    use HasFactory;
    use HasUuids;
    use InteractsWithMedia;
    use SoftDeletes;

    public const LOGO_COLLECTION = 'logo';

    /**
     * @var list<string>
     */
    public const ACCEPTED_LOGO_MIME_TYPES = [
        'image/jpeg',
        'image/png',
        'image/webp',
    ];

    protected $table = 'businesses';

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection(self::LOGO_COLLECTION)
            ->singleFile()
            ->acceptsMimeTypes(self::ACCEPTED_LOGO_MIME_TYPES);
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
        return (int) $this->id;
    }

    /**
     * @return BelongsTo<IndustryModel, $this>
     */
    public function industry(): BelongsTo
    {
        return $this->belongsTo(IndustryModel::class, 'industry_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [

        ];
    }

    protected static function newFactory(): BusinessModelFactory
    {
        return BusinessModelFactory::new();
    }
}
