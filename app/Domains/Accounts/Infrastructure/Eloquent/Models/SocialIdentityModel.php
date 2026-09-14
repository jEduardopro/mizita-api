<?php

declare(strict_types=1);

namespace App\Domains\Accounts\Infrastructure\Eloquent\Models;

use App\Domains\Accounts\ValueObjects\SocialProvider;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Unlinking a provider keeps the history, which is why the provider uniqueness
 * index is partial on deleted_at.
 *
 * Not tenant scoped: Accounts is a root domain.
 */
#[Fillable(['uuid', 'account_id', 'provider', 'provider_user_id'])]
class SocialIdentityModel extends Model
{
    use HasUuids;
    use SoftDeletes;

    protected $table = 'social_identities';

    /**
     * @return BelongsTo<User, $this>
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(User::class, 'account_id');
    }

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
            'provider' => SocialProvider::class,
        ];
    }
}
