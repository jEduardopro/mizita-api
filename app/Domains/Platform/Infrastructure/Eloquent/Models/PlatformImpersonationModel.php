<?php

declare(strict_types=1);

namespace App\Domains\Platform\Infrastructure\Eloquent\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'uuid',
    'platform_admin_id',
    'account_id',
    'business_id',
    'started_at',
    'ended_at',
    'ip_address',
])]
class PlatformImpersonationModel extends Model
{
    use HasUuids;
    use SoftDeletes;

    protected $table = 'platform_impersonations';

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
            'started_at' => 'immutable_datetime',
            'ended_at' => 'immutable_datetime',
        ];
    }
}
