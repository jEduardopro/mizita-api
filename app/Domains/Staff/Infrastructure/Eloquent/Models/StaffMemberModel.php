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
 * Persistence model. Not a domain entity: it carries no business rules and
 * never leaves the infrastructure layer.
 *
 * No BelongsToBusiness, and that is deliberate twice over:
 *
 * - This is the table that resolves the tenant. The query that answers "which
 *   businesses may this account operate?" runs before any context is bound, so
 *   a global scope reading the context would filter by nothing at best, and
 *   would need a tenant to find out what the tenant is at worst.
 * - The trait writes and filters business_id with the context uuid, while this
 *   table's business_id is the int foreign key onto businesses.id. The two
 *   values are not comparable, so the scope would silently match no rows.
 *
 * Isolation for staff reads therefore comes from the repository and the route
 * middleware, as it does everywhere else - the trait was only ever a net.
 *
 * Soft deletes track the record lifecycle: a membership that was given up is
 * kept, and the uniqueness index is partial on deleted_at so that it does not
 * block a future one.
 *
 * The row carries no role. What a membership may do is a Spatie role held by
 * the account and scoped to this business through Spatie's team key, which is
 * this table's business_id value; StaffRoleAssignments is the only class that
 * reads or writes it.
 */
#[Fillable(['uuid', 'business_id', 'account_id'])]
class StaffMemberModel extends Model
{
    use HasFactory;
    use HasUuids;
    use SoftDeletes;

    protected $table = 'staff_members';

    /**
     * The business this membership grants access to, joined on the int key.
     *
     * @return BelongsTo<BusinessModel, $this>
     */
    public function business(): BelongsTo
    {
        return $this->belongsTo(BusinessModel::class, 'business_id');
    }

    /**
     * The account holding the membership, joined on the int key.
     *
     * @return BelongsTo<User, $this>
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(User::class, 'account_id');
    }

    /**
     * The uuid column carries the public identity, so the primary key stays
     * an auto-incrementing int used only for internal joins and indexes.
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
