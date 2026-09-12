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
 * Persistence model. Not a domain entity: it carries no business rules and
 * never leaves the infrastructure layer.
 *
 * Soft deletes track the record lifecycle - unlinking a provider keeps the
 * history - which is why the provider uniqueness index is partial.
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
     * The account this identity signs in, joined on the int primary key.
     *
     * @return BelongsTo<User, $this>
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(User::class, 'account_id');
    }

    /**
     * The uuid column carries the public identity, so the primary key stays an
     * auto-incrementing int used only for internal joins and indexes.
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
