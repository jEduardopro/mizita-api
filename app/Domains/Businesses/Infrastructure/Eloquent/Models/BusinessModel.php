<?php

declare(strict_types=1);

namespace App\Domains\Businesses\Infrastructure\Eloquent\Models;

use App\Domains\Businesses\Infrastructure\Eloquent\Factories\BusinessModelFactory;
use App\Domains\Industries\Infrastructure\Eloquent\Models\IndustryModel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['uuid', 'name', 'slug', 'industry_id', 'timezone'])]
class BusinessModel extends Model
{
    use HasFactory;
    use HasUuids;
    use SoftDeletes;

    protected $table = 'businesses';

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
