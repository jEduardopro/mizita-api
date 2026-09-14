<?php

declare(strict_types=1);

namespace App\Domains\Industries\Infrastructure\Eloquent\Models;

use App\Domains\Industries\Infrastructure\Eloquent\Factories\IndustryModelFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['uuid', 'key', 'position', 'active'])]
class IndustryModel extends Model
{
    use HasFactory;
    use HasUuids;
    use SoftDeletes;

    protected $table = 'industries';

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
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'active' => 'boolean',
        ];
    }

    protected static function newFactory(): IndustryModelFactory
    {
        return IndustryModelFactory::new();
    }
}
