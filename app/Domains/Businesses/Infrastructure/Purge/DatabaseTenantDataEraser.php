<?php

declare(strict_types=1);

namespace App\Domains\Businesses\Infrastructure\Purge;

use App\Domains\Businesses\Contracts\TenantDataEraser;
use App\Domains\Businesses\Exceptions\BusinessNotFound;
use App\Domains\Businesses\Infrastructure\Eloquent\Models\BusinessModel;
use App\Shared\Infrastructure\Eloquent\Models\MediaModel;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;
use Spatie\Permission\PermissionRegistrar;

final class DatabaseTenantDataEraser implements TenantDataEraser
{
    public const ERASED_TABLES = [
        'calendar_event_links',
        'calendar_connections',
        'payment_transactions',
        'payment_items',
        'payments',
        'appointments',
        'phones',
        'addresses',
        'links',
        'schedule_rules',
        'service_staff',
        'services',
        'customers',
        'staff_profiles',
        'staff_members',
        'model_has_permissions',
        'model_has_roles',
        'role_has_permissions',
        'roles',
        'booking_pages',
        'booking_policies',
        'business_payment_methods',
    ];

    public const FILE_TABLES = [
        'media',
    ];

    public const PRESERVED_TABLES = [
        'businesses',
        'users',
        'password_reset_tokens',
        'sessions',
        'personal_access_tokens',
        'social_identities',
        'passkeys',
        'industries',
        'states',
        'payment_methods',
        'permissions',
        'cache',
        'cache_locks',
        'jobs',
        'job_batches',
        'failed_jobs',
        'migrations',
    ];

    private const BUSINESS_COLUMN = 'business_id';

    private const PRIMARY_KEY = 'id';

    private const BUSINESS_MORPH_ALIAS = 'business';

    private const OWNER_TABLES_BY_MORPH_ALIAS = [
        'staff_member' => 'staff_members',
        'staff_profile' => 'staff_profiles',
        'customer' => 'customers',
        'service' => 'services',
        'booking_page' => 'booking_pages',
    ];

    public function __construct(
        private readonly ConnectionInterface $database,
        private readonly PermissionRegistrar $permissions,
    ) {}

    /**
     * @return list<string>
     */
    public static function coveredTables(): array
    {
        return [...self::ERASED_TABLES, ...self::FILE_TABLES];
    }

    public function eraseFilesOf(string $businessId): void
    {
        MediaModel::query()
            ->where(self::BUSINESS_COLUMN, $this->businessKeyOf($businessId))
            ->lazyById()
            ->each(static function (MediaModel $media): void {
                $media->delete();
            });
    }

    public function eraseRecordsOf(string $businessId): void
    {
        $businessKey = $this->businessKeyOf($businessId);

        $this->database->transaction(function () use ($businessKey): void {
            foreach (self::ERASED_TABLES as $table) {
                $this->rowsOwnedBy($table, $businessKey)->delete();
            }
        });

        $this->permissions->forgetCachedPermissions();
    }

    private function businessKeyOf(string $businessId): int
    {
        $key = BusinessModel::withTrashed()
            ->where('uuid', $businessId)
            ->value(self::PRIMARY_KEY);

        if ($key === null) {
            throw BusinessNotFound::withId($businessId);
        }

        return (int) $key;
    }

    private function rowsOwnedBy(string $table, int $businessKey): Builder
    {
        return match ($table) {
            'payment_transactions', 'payment_items' => $this->rowsOf($table)
                ->whereIn('payment_id', $this->keysOf('payments', $businessKey)),
            'phones' => $this->polymorphicRowsOwnedBy($table, 'phoneable', $businessKey),
            'addresses' => $this->polymorphicRowsOwnedBy($table, 'addressable', $businessKey),
            'links' => $this->polymorphicRowsOwnedBy($table, 'linkable', $businessKey),
            'service_staff' => $this->rowsOf($table)
                ->whereIn('service_id', $this->keysOf('services', $businessKey))
                ->orWhereIn('staff_member_id', $this->keysOf('staff_members', $businessKey)),
            'role_has_permissions' => $this->rowsOf($table)
                ->whereIn('role_id', $this->keysOf('roles', $businessKey)),
            default => $this->rowsOf($table)->where(self::BUSINESS_COLUMN, $businessKey),
        };
    }

    private function polymorphicRowsOwnedBy(string $table, string $morphName, int $businessKey): Builder
    {
        $typeColumn = $morphName.'_type';
        $idColumn = $morphName.'_id';

        return $this->rowsOf($table)->where(function (Builder $owners) use ($typeColumn, $idColumn, $businessKey): void {
            $owners->where(static fn (Builder $business): Builder => $business
                ->where($typeColumn, self::BUSINESS_MORPH_ALIAS)
                ->where($idColumn, $businessKey));

            foreach (self::OWNER_TABLES_BY_MORPH_ALIAS as $morphAlias => $ownerTable) {
                $owners->orWhere(fn (Builder $owner): Builder => $owner
                    ->where($typeColumn, $morphAlias)
                    ->whereIn($idColumn, $this->keysOf($ownerTable, $businessKey)));
            }
        });
    }

    private function keysOf(string $table, int $businessKey): Builder
    {
        return $this->rowsOf($table)
            ->select(self::PRIMARY_KEY)
            ->where(self::BUSINESS_COLUMN, $businessKey);
    }

    private function rowsOf(string $table): Builder
    {
        return $this->database->table($table);
    }
}
