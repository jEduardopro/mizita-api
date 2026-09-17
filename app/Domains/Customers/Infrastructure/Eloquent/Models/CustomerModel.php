<?php

declare(strict_types=1);

namespace App\Domains\Customers\Infrastructure\Eloquent\Models;

use App\Domains\Businesses\Infrastructure\Eloquent\Models\BusinessModel;
use App\Domains\Customers\Infrastructure\Eloquent\Factories\CustomerModelFactory;
use App\Shared\Infrastructure\Media\BusinessScopedMediaOwner;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

#[Fillable(['uuid', 'business_id', 'name', 'email', 'birth_date', 'notes'])]
class CustomerModel extends Model implements BusinessScopedMediaOwner, HasMedia
{
    use HasFactory;
    use HasUuids;
    use InteractsWithMedia;
    use SoftDeletes;

    public const PHOTO_COLLECTION = 'photo';

    /**
     * @var list<string>
     */
    public const ACCEPTED_PHOTO_MIME_TYPES = [
        'image/jpeg',
        'image/png',
        'image/webp',
    ];

    protected $table = 'customers';

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection(self::PHOTO_COLLECTION)
            ->singleFile()
            ->acceptsMimeTypes(self::ACCEPTED_PHOTO_MIME_TYPES);
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
            'birth_date' => 'immutable_date',
        ];
    }

    protected static function newFactory(): CustomerModelFactory
    {
        return CustomerModelFactory::new();
    }
}
