<?php

declare(strict_types=1);

namespace App\Domains\Services\Infrastructure\Eloquent\Models;

use App\Domains\Services\Infrastructure\Eloquent\Factories\ServiceModelFactory;
use App\Domains\Services\ValueObjects\ServiceColor;
use App\Domains\Staff\Infrastructure\Eloquent\Models\StaffMemberModel;
use App\Shared\Infrastructure\Media\BusinessScopedMediaOwner;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

#[Fillable(['uuid', 'business_id', 'name', 'slug', 'description', 'duration_minutes', 'buffer_minutes', 'price', 'color', 'active'])]
class ServiceModel extends Model implements BusinessScopedMediaOwner, HasMedia
{
    use HasFactory;
    use HasUuids;
    use InteractsWithMedia;
    use SoftDeletes;

    public const IMAGE_COLLECTION = 'image';

    /**
     * @var list<string>
     */
    public const ACCEPTED_IMAGE_MIME_TYPES = [
        'image/jpeg',
        'image/png',
        'image/webp',
    ];

    public const STAFF_PIVOT_TABLE = 'service_staff';

    protected $table = 'services';

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection(self::IMAGE_COLLECTION)
            ->singleFile()
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
     * @return BelongsToMany<StaffMemberModel, $this>
     */
    public function staffMembers(): BelongsToMany
    {
        return $this->belongsToMany(
            StaffMemberModel::class,
            self::STAFF_PIVOT_TABLE,
            'service_id',
            'staff_member_id',
        );
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'duration_minutes' => 'integer',
            'buffer_minutes' => 'integer',
            'price' => 'decimal:2',
            'color' => ServiceColor::class,
            'active' => 'boolean',
        ];
    }

    protected static function newFactory(): ServiceModelFactory
    {
        return ServiceModelFactory::new();
    }
}
