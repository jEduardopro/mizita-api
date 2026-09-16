<?php

declare(strict_types=1);

namespace App\Domains\Links\Infrastructure\Eloquent\Models;

use App\Domains\Links\Infrastructure\Eloquent\Factories\LinkModelFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['uuid', 'linkable_type', 'linkable_id', 'platform', 'url', 'position'])]
class LinkModel extends Model
{
    use HasFactory;
    use HasUuids;
    use SoftDeletes;

    protected $table = 'links';

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
        ];
    }

    protected static function newFactory(): LinkModelFactory
    {
        return LinkModelFactory::new();
    }
}
