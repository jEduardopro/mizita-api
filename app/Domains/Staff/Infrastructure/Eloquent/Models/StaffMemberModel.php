<?php

declare(strict_types=1);

namespace App\Domains\Staff\Infrastructure\Eloquent\Models;

use App\Domains\Businesses\Infrastructure\Eloquent\Models\BusinessModel;
use App\Domains\Staff\Infrastructure\Eloquent\Factories\StaffMemberModelFactory;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * No BelongsToBusiness, deliberately twice over:
 *
 * - This is the table that resolves the tenant. The query answering "which
 *   businesses may this account operate?" runs before any context is bound.
 * - The trait writes and filters business_id with the context uuid, while this
 *   table's business_id is the int foreign key onto businesses.id. The two
 *   values are not comparable, so the scope would silently match no rows.
 *
 * The uniqueness index is partial on deleted_at, so a membership that was given
 * up is kept without blocking a future one.
 *
 * The row carries no role. What a membership may do is a Spatie role held by the
 * account and scoped to this business through Spatie's team key, which is this
 * table's business_id value; StaffRoleAssignments is the only class that touches it.
 */
#[Fillable(['uuid', 'business_id', 'account_id'])]
class StaffMemberModel extends Model
{
    use HasFactory;
    use HasUuids;
    use SoftDeletes;

    protected $table = 'staff_members';

    /**
     * @return BelongsTo<BusinessModel, $this>
     */
    public function business(): BelongsTo
    {
        return $this->belongsTo(BusinessModel::class, 'business_id');
    }

    /**
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

    protected static function newFactory(): StaffMemberModelFactory
    {
        return StaffMemberModelFactory::new();
    }
}
