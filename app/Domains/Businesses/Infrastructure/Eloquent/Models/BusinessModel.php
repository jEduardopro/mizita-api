<?php

declare(strict_types=1);

namespace App\Domains\Businesses\Infrastructure\Eloquent\Models;

use App\Domains\Businesses\Infrastructure\Eloquent\Factories\BusinessModelFactory;
use App\Domains\Industries\Infrastructure\Eloquent\Models\IndustryModel;
use App\Models\User;
use App\Shared\Infrastructure\Media\BusinessScopedMediaOwner;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

#[Fillable([
    'uuid',
    'name',
    'slug',
    'industry_id',
    'timezone',
    'contact_email',
    'about',
    'currency_code',
    'closed_at',
    'closed_by_account_id',
    'purged_at',
    'deleted_at',
])]
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
     * @return BelongsTo<User, $this>
     */
    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by_account_id')->withTrashed();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'closed_at' => 'immutable_datetime',
            'purged_at' => 'immutable_datetime',
        ];
    }

    protected static function newFactory(): BusinessModelFactory
    {
        return BusinessModelFactory::new();
    }
}
