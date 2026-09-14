<?php

declare(strict_types=1);

namespace App\Domains\Phones\Infrastructure\Eloquent\Models;

use App\Domains\Phones\Infrastructure\Eloquent\Factories\PhoneModelFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

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
