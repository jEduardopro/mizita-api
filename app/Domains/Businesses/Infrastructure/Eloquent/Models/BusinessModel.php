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

/**
 * Persistence model. Not a domain entity: it carries no business rules and
 * never leaves the infrastructure layer.
 *
 * Soft deletes track the record lifecycle. Any "active" style flag is a
 * separate business state and lives on the entity.
 */
#[Fillable(['uuid', 'name', 'slug', 'industry_id', 'timezone'])]
class BusinessModel extends Model
{
    use HasFactory;
    use HasUuids;
    use SoftDeletes;

    protected $table = 'businesses';

    /**
     * The uuid column carries the public identity, so the primary key stays
     * an auto-incrementing int used only for internal joins and indexes.
     *
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
     * The catalog row this business belongs to.
     *
     * It exists so the repository can read the industry's uuid back without a
     * query per row: the foreign key holds the int, the entity carries the
     * uuid, and eager loading this relation is what joins the two halves.
     *
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
