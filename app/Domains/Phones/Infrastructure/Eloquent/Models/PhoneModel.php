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
 * There is deliberately no morphTo relation and no enum cast on phoneable_type:
 * this domain reads the owner column as an opaque string and PhoneMapper turns
 * it into a PhoneOwnerType. Resolving the owning model here would make Phones
 * import every domain that owns a phone.
 */
#[Fillable([
    'uuid',
    'phoneable_type',
    'phoneable_id',
    'country_code',
    'national_number',
    'calling_code',
    'e164',
    'number_type',
    'geo_description',
    'timezones',
])]
class PhoneModel extends Model
{
    use HasFactory;
    use HasUuids;
    use SoftDeletes;

    protected $table = 'phones';

    /**
     * Overridden so the primary key stays an auto-incrementing int; uuid carries
     * the public identity.
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
     * There is deliberately no cast on number_type. An enum cast would put a
     * shared value object in the model's signature, and PhoneMapper is what
     * owns the translation between a stored string and a PhoneNumberType.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'calling_code' => 'integer',
            'timezones' => 'array',
        ];
    }

    protected static function newFactory(): PhoneModelFactory
    {
        return PhoneModelFactory::new();
    }
}
