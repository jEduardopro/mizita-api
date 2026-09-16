<?php

declare(strict_types=1);

namespace App\Domains\Addresses\Infrastructure\Eloquent\Models;

use App\Domains\Addresses\Infrastructure\Eloquent\Factories\StateModelFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['uuid', 'country_code', 'code', 'name', 'position', 'active'])]
class StateModel extends Model
{
    use HasFactory;
    use HasUuids;
    use SoftDeletes;

    protected $table = 'states';

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
            'position' => 'integer',
            'active' => 'boolean',
        ];
    }

    protected static function newFactory(): StateModelFactory
    {
        return StateModelFactory::new();
    }
}
