<?php

declare(strict_types=1);

namespace App\Domains\Platform\Infrastructure\Eloquent\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;

#[Fillable([
    'uuid',
    'name',
    'email',
    'password',
    'two_factor_secret',
    'two_factor_confirmed_at',
    'last_login_at',
])]
#[Hidden(['password', 'two_factor_secret'])]
class PlatformAdminModel extends Authenticatable
{
    use HasUuids;
    use SoftDeletes;

    private const NO_REMEMBER_TOKEN = '';

    protected $table = 'platform_admins';

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

    public function getRememberTokenName(): string
    {
        return self::NO_REMEMBER_TOKEN;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'two_factor_secret' => 'encrypted',
            'two_factor_confirmed_at' => 'immutable_datetime',
            'last_login_at' => 'immutable_datetime',
        ];
    }
}
