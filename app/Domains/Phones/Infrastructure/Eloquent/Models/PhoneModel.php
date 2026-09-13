<?php

declare(strict_types=1);

namespace App\Domains\Phones\Infrastructure\Eloquent\Models;

use App\Domains\Phones\Infrastructure\Eloquent\Factories\PhoneModelFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Persistence model. Not a domain entity: it carries no business rules and
 * never leaves the infrastructure layer.
 *
 * There is deliberately no morphTo relation and no enum cast on
 * phoneable_type: this domain reads the owner column as an opaque string, and
 * PhoneMapper is what turns it into a PhoneOwnerType. Resolving the owning
 * model here would make Phones import every domain that owns a phone.
 *
 * Soft deletes track the record lifecycle.
 */
#[Fillable(['uuid', 'phoneable_type', 'phoneable_id', 'country_code', 'national_number'])]
class PhoneModel extends Model
{
    use HasFactory;
    use HasUuids;
    use SoftDeletes;

    protected $table = 'phones';

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
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [];
    }

    protected static function newFactory(): PhoneModelFactory
    {
        return PhoneModelFactory::new();
    }
}
