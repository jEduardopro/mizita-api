<?php

declare(strict_types=1);

namespace App\Domains\Accounts\Infrastructure\Eloquent\Models;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;
use Laravel\Passkeys\Passkey;

/**
 * @property string $uuid
 */
class PasskeyModel extends Passkey
{
    use HasUuids;
    use SoftDeletes;

    protected $table = 'passkeys';

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
     * @return BelongsTo<Model, $this>
     */
    public function user(): BelongsTo
    {
        return parent::user()->withTrashed();
    }

    public function resolveRouteBinding($value, $field = null): ?Model
    {
        $owner = Auth::guard(Config::string('passkeys.guard'))->user();

        if (! $owner instanceof Authenticatable || ! is_string($value) || ! Str::isUuid($value)) {
            return null;
        }

        return $this->newQuery()
            ->where($this->getRouteKeyName(), $value)
            ->where('user_id', $owner->getAuthIdentifier())
            ->first();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            ...parent::casts(),
            'user_id' => 'integer',
        ];
    }
}
