<?php

declare(strict_types=1);

namespace App\Domains\Addresses\Infrastructure\Eloquent\Models;

use App\Domains\Addresses\Infrastructure\Eloquent\Factories\AddressModelFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'uuid',
    'addressable_type',
    'addressable_id',
    'street',
    'city',
    'state_id',
    'postal_code',
    'country_code',
    'latitude',
    'longitude',
])]
class AddressModel extends Model
{
    use HasFactory;
    use HasUuids;
    use SoftDeletes;

    protected $table = 'addresses';

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
     * @return BelongsTo<StateModel, $this>
     */
    public function state(): BelongsTo
    {
        return $this->belongsTo(StateModel::class, 'state_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'latitude' => 'float',
            'longitude' => 'float',
        ];
    }

    protected static function newFactory(): AddressModelFactory
    {
        return AddressModelFactory::new();
    }
}
