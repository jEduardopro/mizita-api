<?php

declare(strict_types=1);

namespace App\Domains\Platform\Infrastructure\Gateways;

use App\Domains\Businesses\Infrastructure\Eloquent\Models\BusinessModel;
use App\Domains\Platform\Contracts\PlatformBusinessDirectory;
use App\Domains\Platform\ValueObjects\PlatformBusinessOwner;
use App\Domains\Platform\ValueObjects\PlatformBusinessQuery;
use App\Domains\Platform\ValueObjects\PlatformBusinessRecord;
use App\Domains\Platform\ValueObjects\PlatformBusinessSort;
use App\Domains\Staff\Infrastructure\Permissions\SeededStaffRole;
use App\Domains\Staff\Infrastructure\Permissions\StaffRoleAssignments;
use App\Models\User;
use App\Shared\Infrastructure\Search\SearchableColumns;
use App\Shared\Infrastructure\Search\TokenSearch;
use App\Shared\ValueObjects\Paginated;
use App\Shared\ValueObjects\SearchTerm;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use stdClass;

final class EloquentPlatformBusinessDirectory implements PlatformBusinessDirectory
{
    private const OWNER_ASSIGNMENTS = 'owner_assignments';

    private const OWNER_ACCOUNT_KEY = 'account_key';

    private const OWNERS = 'owners';

    private const USERS_TABLE = 'users';

    private const SERVICES_TABLE = 'services';

    private const CUSTOMERS_TABLE = 'customers';

    private const SERVICES_COUNT = 'services_count';

    private const CUSTOMERS_COUNT = 'customers_count';

    private const TIEBREAKER_COLUMN = 'businesses.id';

    private const STORAGE_TIMEZONE = 'UTC';

    /**
     * @var list<string>
     */
    private const COLUMNS = [
        'businesses.uuid as id',
        'businesses.name as name',
        'businesses.slug as slug',
        'businesses.created_at as created_at',
        'owners.name as owner_name',
        'owners.email as owner_email',
    ];

    public function __construct(
        private readonly TokenSearch $tokenSearch,
    ) {}

    /**
     * @return Paginated<PlatformBusinessRecord>
     */
    public function page(PlatformBusinessQuery $query): Paginated
    {
        $matching = $this->matching($query->search);
        $total = $matching->count();

        $rows = $matching
            ->select(self::COLUMNS)
            ->selectSub(self::liveRowCountOf(self::SERVICES_TABLE), self::SERVICES_COUNT)
            ->selectSub(self::liveRowCountOf(self::CUSTOMERS_TABLE), self::CUSTOMERS_COUNT)
            ->orderBy(self::sortColumnFor($query->sort), $query->direction->value)
            ->orderBy(self::TIEBREAKER_COLUMN)
            ->offset($query->pagination->offset())
            ->limit($query->pagination->perPage)
            ->get();

        return Paginated::of(
            array_values(array_map(self::recordFrom(...), $rows->all())),
            $total,
            $query->pagination,
        );
    }

    private function matching(?SearchTerm $search): Builder
    {
        $businesses = BusinessModel::query()
            ->leftJoinSub(
                self::ownerAssignments(),
                self::OWNER_ASSIGNMENTS,
                self::OWNER_ASSIGNMENTS.'.'.StaffRoleAssignments::TEAM_COLUMN,
                '=',
                'businesses.id',
            )
            ->leftJoin(
                self::USERS_TABLE.' as '.self::OWNERS,
                self::OWNERS.'.id',
                '=',
                self::OWNER_ASSIGNMENTS.'.'.self::OWNER_ACCOUNT_KEY,
            );

        if ($search !== null) {
            $this->tokenSearch->apply($businesses, $search, self::searchableColumns());
        }

        return $businesses->toBase();
    }

    private static function ownerAssignments(): Builder
    {
        return DB::table(StaffRoleAssignments::ASSIGNMENTS_TABLE)
            ->select(StaffRoleAssignments::TEAM_COLUMN)
            ->selectRaw('min(model_id) as '.self::OWNER_ACCOUNT_KEY)
            ->where('role_id', SeededStaffRole::OWNER_ID)
            ->where('model_type', (new User)->getMorphClass())
            ->groupBy(StaffRoleAssignments::TEAM_COLUMN);
    }

    private static function liveRowCountOf(string $table): Builder
    {
        return DB::table($table)
            ->selectRaw('count(*)')
            ->whereColumn($table.'.business_id', 'businesses.id')
            ->whereNull($table.'.deleted_at');
    }

    private static function searchableColumns(): SearchableColumns
    {
        return SearchableColumns::text('businesses.name', self::OWNERS.'.name', self::OWNERS.'.email');
    }

    private static function sortColumnFor(PlatformBusinessSort $sort): string
    {
        return match ($sort) {
            PlatformBusinessSort::CreatedAt => 'businesses.created_at',
            PlatformBusinessSort::Name => 'businesses.name',
            PlatformBusinessSort::ServicesCount => self::SERVICES_COUNT,
            PlatformBusinessSort::CustomersCount => self::CUSTOMERS_COUNT,
        };
    }

    private static function recordFrom(stdClass $row): PlatformBusinessRecord
    {
        return new PlatformBusinessRecord(
            id: (string) $row->id,
            name: (string) $row->name,
            slug: (string) $row->slug,
            createdAt: self::instantFrom((string) $row->created_at),
            owner: self::ownerFrom($row),
            servicesCount: (int) $row->services_count,
            customersCount: (int) $row->customers_count,
        );
    }

    private static function ownerFrom(stdClass $row): ?PlatformBusinessOwner
    {
        if ($row->owner_email === null) {
            return null;
        }

        return new PlatformBusinessOwner(
            name: (string) $row->owner_name,
            email: (string) $row->owner_email,
        );
    }

    private static function instantFrom(string $stored): DateTimeImmutable
    {
        $storageZone = new DateTimeZone(self::STORAGE_TIMEZONE);

        return (new DateTimeImmutable($stored, $storageZone))->setTimezone($storageZone);
    }
}
