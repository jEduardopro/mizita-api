<?php

declare(strict_types=1);

namespace App\Domains\Staff\Infrastructure\Eloquent;

use App\Domains\Businesses\Infrastructure\Eloquent\Models\BusinessModel;
use App\Domains\Staff\Contracts\StaffMemberRepository;
use App\Domains\Staff\Contracts\TeamRoster;
use App\Domains\Staff\Entities\StaffMember;
use App\Domains\Staff\Exceptions\AccountAlreadyOwnsBusiness;
use App\Domains\Staff\Exceptions\StaffMemberNotFound;
use App\Domains\Staff\Exceptions\TeamMemberAlreadyExists;
use App\Domains\Staff\Infrastructure\Eloquent\Mappers\StaffMemberMapper;
use App\Domains\Staff\Infrastructure\Eloquent\Models\StaffMemberModel;
use App\Domains\Staff\Infrastructure\Eloquent\Models\StaffProfileModel;
use App\Domains\Staff\Infrastructure\Permissions\StaffRoleAssignments;
use App\Domains\Staff\ValueObjects\TeamQuery;
use App\Domains\Staff\ValueObjects\TeamSort;
use App\Models\User;
use App\Shared\Infrastructure\Search\SearchableColumns;
use App\Shared\Infrastructure\Search\TokenSearch;
use App\Shared\ValueObjects\Paginated;
use App\Shared\ValueObjects\SearchTerm;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Database\Query\Expression;
use Illuminate\Database\UniqueConstraintViolationException;

final class EloquentStaffMemberRepository implements StaffMemberRepository, TeamRoster
{
    private const BUSINESS_ACCOUNT_UNIQUE_INDEX = 'staff_members_business_account_unique';

    private const USERS_TABLE = 'users';

    private const STAFF_PROFILES_TABLE = 'staff_profiles';

    private const TIEBREAKER_COLUMN = 'staff_members.id';

    public function __construct(
        private readonly StaffMemberMapper $mapper,
        private readonly StaffRoleAssignments $roles,
        private readonly TokenSearch $tokenSearch,
    ) {}

    public function save(StaffMember $member): void
    {
        $account = $this->accountFor($member->accountId);
        $businessKey = $this->businessKey($member->businessId);

        try {
            StaffMemberModel::query()->updateOrCreate(
                ['uuid' => $member->id],
                $this->mapper->toAttributes($member, $businessKey, $account->getKey()),
            );

            $this->roles->assign($account, $businessKey, $member->role());
        } catch (UniqueConstraintViolationException $violation) {
            $this->failFrom($member, $violation);
        }
    }

    public function findById(string $id): StaffMember
    {
        $model = StaffMemberModel::query()
            ->with(['business', 'account'])
            ->where('uuid', $id)
            ->first();

        $business = $model?->business;
        $account = $model?->account;

        if ($model === null || $business === null || $account === null) {
            throw StaffMemberNotFound::withId($id);
        }

        $role = $this->roles->roleFor($account, (int) $model->business_id);

        if ($role === null) {
            throw StaffMemberNotFound::withId($id);
        }

        return $this->mapper->toEntity($model, $business->uuid, $account->uuid, $role);
    }

    public function findForBusiness(string $businessId, string $id): StaffMember
    {
        $businessKey = $this->businessKey($businessId);

        $model = StaffMemberModel::query()
            ->with('account')
            ->where('business_id', $businessKey)
            ->where('uuid', $id)
            ->first();

        return $this->hydrateOrFail($model, $businessId, $businessKey, StaffMemberNotFound::withId($id));
    }

    public function findForAccount(string $businessId, string $accountId): StaffMember
    {
        $businessKey = $this->businessKey($businessId);

        $model = StaffMemberModel::query()
            ->with('account')
            ->where('business_id', $businessKey)
            ->whereRelation('account', 'uuid', $accountId)
            ->first();

        return $this->hydrateOrFail($model, $businessId, $businessKey, StaffMemberNotFound::forAccount($accountId));
    }

    /**
     * @return list<StaffMember>
     */
    public function allForBusiness(string $businessId): array
    {
        $businessKey = $this->businessKey($businessId);

        return $this->membersOf(
            $businessId,
            $businessKey,
            $this->ofBusinessKey($businessKey)->get(),
        );
    }

    /**
     * @param  list<string>  $ids
     * @return list<StaffMember>
     */
    public function findManyIncludingArchived(string $businessId, array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        $businessKey = $this->businessKey($businessId);

        return $this->membersOf(
            $businessId,
            $businessKey,
            $this->ofBusinessKey($businessKey)->withTrashed()->whereIn('uuid', $ids)->get(),
        );
    }

    public function ownsAnyBusiness(string $accountId): bool
    {
        return $this->roles->ownsAnyBusiness($this->accountFor($accountId));
    }

    public function delete(string $businessId, string $id): void
    {
        $businessKey = $this->businessKey($businessId);

        $model = StaffMemberModel::query()
            ->with('account')
            ->where('business_id', $businessKey)
            ->where('uuid', $id)
            ->first();

        $account = $model?->account;

        if ($model === null || $account === null) {
            throw StaffMemberNotFound::withId($id);
        }

        StaffProfileModel::query()->where('staff_member_id', $model->getKey())->delete();

        $this->roles->revoke($account, $businessKey);

        $model->delete();
    }

    /**
     * @return Paginated<StaffMember>
     */
    public function search(string $businessId, TeamQuery $query): Paginated
    {
        $businessKey = $this->businessKey($businessId);
        $matching = $this->matching($businessKey, $query);
        $total = $matching->count();

        $models = $this->mostRelevantFirst($matching, $query->search)
            ->orderBy(self::columnFor($query->sort), $query->direction->value)
            ->orderBy(self::TIEBREAKER_COLUMN)
            ->offset($query->pagination->offset())
            ->limit($query->pagination->perPage)
            ->get();

        return Paginated::of(
            $this->membersOf($businessId, $businessKey, $models),
            $total,
            $query->pagination,
        );
    }

    /**
     * @param  list<string>  $emails
     * @return list<string>
     */
    public function emailsAlreadyOnTeam(string $businessId, array $emails): array
    {
        if ($emails === []) {
            return [];
        }

        $lowered = array_values(array_unique(array_map(
            static fn (string $email): string => mb_strtolower(trim($email)),
            $emails,
        )));

        return $this->withAccounts()
            ->where('staff_members.business_id', $this->businessKey($businessId))
            ->whereIn(new Expression('lower('.self::USERS_TABLE.'.email)'), $lowered)
            ->pluck(self::USERS_TABLE.'.email')
            ->map(static fn (mixed $email): string => mb_strtolower((string) $email))
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return Builder<StaffMemberModel>
     */
    private function ofBusinessKey(int $businessKey): Builder
    {
        return StaffMemberModel::query()
            ->with('account')
            ->where('business_id', $businessKey)
            ->orderBy('created_at')
            ->orderBy('id');
    }

    /**
     * @return Builder<StaffMemberModel>
     */
    private function withAccounts(): Builder
    {
        return StaffMemberModel::query()
            ->join(self::USERS_TABLE, self::USERS_TABLE.'.id', '=', 'staff_members.account_id');
    }

    /**
     * @return Builder<StaffMemberModel>
     */
    private function matching(int $businessKey, TeamQuery $query): Builder
    {
        $scoped = $this->withAccounts()
            ->select('staff_members.*')
            ->with('account')
            ->where('staff_members.business_id', $businessKey);

        $search = $query->search;

        if ($search === null) {
            return $scoped;
        }

        $profileIds = $query->profileIdsMatchingPhone;

        return $scoped->where(function (Builder $matches) use ($search, $profileIds): void {
            $this->tokenSearch->apply($matches, $search, self::searchableColumns());

            if ($profileIds === []) {
                return;
            }

            $matches->orWhereIn(
                'staff_members.id',
                static fn (QueryBuilder $profiles) => $profiles
                    ->select('staff_member_id')
                    ->from(self::STAFF_PROFILES_TABLE)
                    ->whereIn('uuid', $profileIds)
                    ->whereNull('deleted_at'),
            );
        });
    }

    /**
     * @param  Builder<StaffMemberModel>  $query
     * @return Builder<StaffMemberModel>
     */
    private function mostRelevantFirst(Builder $query, ?SearchTerm $search): Builder
    {
        if ($search === null) {
            return $query;
        }

        return $this->tokenSearch->orderByRelevance($query, $search, self::searchableColumns());
    }

    private static function searchableColumns(): SearchableColumns
    {
        return SearchableColumns::text(self::USERS_TABLE.'.name', self::USERS_TABLE.'.email');
    }

    private static function columnFor(TeamSort $sort): string
    {
        return match ($sort) {
            TeamSort::Name => self::USERS_TABLE.'.name',
            TeamSort::CreatedAt => 'staff_members.created_at',
        };
    }

    private function hydrateOrFail(
        ?StaffMemberModel $model,
        string $businessId,
        int $businessKey,
        StaffMemberNotFound $whenMissing,
    ): StaffMember {
        $account = $model?->account;

        if ($model === null || $account === null) {
            throw $whenMissing;
        }

        $role = $this->roles->roleFor($account, $businessKey);

        if ($role === null) {
            throw $whenMissing;
        }

        return $this->mapper->toEntity($model, $businessId, $account->uuid, $role);
    }

    /**
     * @param  Collection<int, StaffMemberModel>  $models
     * @return list<StaffMember>
     */
    private function membersOf(string $businessId, int $businessKey, Collection $models): array
    {
        /** @var list<int> $accountKeys */
        $accountKeys = $models
            ->map(static fn (StaffMemberModel $model): int => (int) $model->account_id)
            ->unique()
            ->values()
            ->all();

        $roles = $this->roles->rolesFor($accountKeys, $businessKey);

        $members = [];

        foreach ($models as $model) {
            $account = $model->account;
            $role = $roles[(int) $model->account_id] ?? null;

            if ($account === null || $role === null) {
                continue;
            }

            $members[] = $this->mapper->toEntity($model, $businessId, $account->uuid, $role);
        }

        return $members;
    }

    private function failFrom(StaffMember $member, UniqueConstraintViolationException $violation): never
    {
        if (str_contains($violation->getMessage(), self::BUSINESS_ACCOUNT_UNIQUE_INDEX)) {
            throw TeamMemberAlreadyExists::forAccount($member->accountId, $violation);
        }

        throw AccountAlreadyOwnsBusiness::forAccount($member->accountId, $violation);
    }

    private function businessKey(string $businessId): int
    {
        $key = BusinessModel::query()->where('uuid', $businessId)->value('id');

        if ($key === null) {
            throw (new ModelNotFoundException)->setModel(BusinessModel::class, [$businessId]);
        }

        return (int) $key;
    }

    private function accountFor(string $accountId): User
    {
        $account = User::query()->where('uuid', $accountId)->first();

        if ($account === null) {
            throw (new ModelNotFoundException)->setModel(User::class, [$accountId]);
        }

        return $account;
    }
}
