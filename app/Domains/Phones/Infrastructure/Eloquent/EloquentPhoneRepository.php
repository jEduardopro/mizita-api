<?php

declare(strict_types=1);

namespace App\Domains\Phones\Infrastructure\Eloquent;

use App\Domains\Phones\Contracts\PhoneRepository;
use App\Domains\Phones\Entities\Phone;
use App\Domains\Phones\Infrastructure\Eloquent\Mappers\PhoneMapper;
use App\Domains\Phones\Infrastructure\Eloquent\Models\PhoneModel;
use App\Domains\Phones\ValueObjects\PhoneNumberFragment;
use App\Domains\Phones\ValueObjects\PhoneOwnerType;
use App\Shared\Contracts\BusinessTeamKey;
use App\Shared\ValueObjects\PhoneNumber;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Query\JoinClause;

final class EloquentPhoneRepository implements PhoneRepository
{
    private const PHONES_TABLE = 'phones';

    private const PHONE_NUMBER_COLUMN = self::PHONES_TABLE.'.e164';

    private const OWNER_BUSINESS_COLUMN = 'business_id';

    private const OWNER_IDENTITY_COLUMN = 'uuid';

    public function __construct(
        private readonly PhoneMapper $mapper,
        private readonly BusinessTeamKey $businessKeys,
    ) {}

    public function findForOwner(PhoneOwnerType $ownerType, string $ownerId): ?Phone
    {
        $model = $this->ownedBy($ownerType, $ownerId)->first();

        if ($model === null) {
            return null;
        }

        return $this->mapper->toEntity($model, $ownerId);
    }

    /**
     * @param  list<string>  $ownerIds
     * @return array<string, Phone>
     */
    public function findForOwners(PhoneOwnerType $ownerType, array $ownerIds): array
    {
        if ($ownerIds === []) {
            return [];
        }

        $ownerIdsByKey = $this->ownerIdsByKey($ownerType, $ownerIds);

        if ($ownerIdsByKey === []) {
            return [];
        }

        $models = PhoneModel::query()
            ->where('phoneable_type', $ownerType->value)
            ->whereIn('phoneable_id', array_keys($ownerIdsByKey))
            ->get();

        $phones = [];

        foreach ($models as $model) {
            $ownerId = $ownerIdsByKey[(int) $model->phoneable_id];

            $phones[$ownerId] = $this->mapper->toEntity($model, $ownerId);
        }

        return $phones;
    }

    /**
     * @return list<string>
     */
    public function ownerIdsWithNumber(PhoneOwnerType $ownerType, string $businessId, PhoneNumber $number): array
    {
        return $this->ownerIdsOf(
            $this->businessOwnersWithPhone($ownerType, $businessId)
                ->where(self::PHONE_NUMBER_COLUMN, $number->e164()),
        );
    }

    /**
     * @return list<string>
     */
    public function ownerIdsMatchingNumber(PhoneOwnerType $ownerType, string $businessId, PhoneNumberFragment $fragment): array
    {
        return $this->ownerIdsOf(
            $this->businessOwnersWithPhone($ownerType, $businessId)
                ->where(self::PHONE_NUMBER_COLUMN, 'like', '%'.$fragment->digits.'%'),
        );
    }

    public function save(Phone $phone): void
    {
        PhoneModel::query()->updateOrCreate(
            ['uuid' => $phone->id],
            $this->mapper->toAttributes($phone, $this->ownerKey($phone->ownerType, $phone->ownerId)),
        );
    }

    public function deleteForOwner(PhoneOwnerType $ownerType, string $ownerId): void
    {
        $this->ownedBy($ownerType, $ownerId)->delete();
    }

    /**
     * @return Builder<PhoneModel>
     */
    private function ownedBy(PhoneOwnerType $ownerType, string $ownerId): Builder
    {
        return PhoneModel::query()
            ->where('phoneable_type', $ownerType->value)
            ->where('phoneable_id', $this->ownerKey($ownerType, $ownerId));
    }

    /**
     * @return Builder<Model>
     */
    private function businessOwnersWithPhone(PhoneOwnerType $ownerType, string $businessId): Builder
    {
        $ownerClass = $this->ownerModel($ownerType, []);
        $owner = new $ownerClass;

        return $owner->newQuery()
            ->join(self::PHONES_TABLE, static function (JoinClause $join) use ($owner, $ownerType): void {
                $join->on(self::PHONES_TABLE.'.phoneable_id', '=', $owner->getQualifiedKeyName())
                    ->where(self::PHONES_TABLE.'.phoneable_type', '=', $ownerType->value)
                    ->whereNull(self::PHONES_TABLE.'.deleted_at');
            })
            ->where($owner->qualifyColumn(self::OWNER_BUSINESS_COLUMN), $this->businessKeys->teamKeyFor($businessId))
            ->orderBy($owner->getQualifiedKeyName())
            ->limit(PhoneRepository::MAXIMUM_OWNER_MATCHES);
    }

    /**
     * @param  Builder<Model>  $owners
     * @return list<string>
     */
    private function ownerIdsOf(Builder $owners): array
    {
        return $owners
            ->pluck($owners->getModel()->qualifyColumn(self::OWNER_IDENTITY_COLUMN))
            ->map(static fn (mixed $uuid): string => (string) $uuid)
            ->values()
            ->all();
    }

    private function ownerKey(PhoneOwnerType $ownerType, string $ownerId): int
    {
        $owner = $this->ownerModel($ownerType, [$ownerId]);

        $key = $owner::query()->where('uuid', $ownerId)->value('id');

        if ($key === null) {
            throw (new ModelNotFoundException)->setModel($owner, [$ownerId]);
        }

        return (int) $key;
    }

    /**
     * @param  list<string>  $ownerIds
     * @return array<int, string>
     */
    private function ownerIdsByKey(PhoneOwnerType $ownerType, array $ownerIds): array
    {
        $owner = $this->ownerModel($ownerType, $ownerIds);

        /** @var array<int, string> $identities */
        $identities = $owner::query()
            ->whereIn('uuid', $ownerIds)
            ->pluck('uuid', 'id')
            ->map(static fn (mixed $uuid): string => (string) $uuid)
            ->all();

        return $identities;
    }

    /**
     * @param  list<string>  $ownerIds
     * @return class-string<Model>
     */
    private function ownerModel(PhoneOwnerType $ownerType, array $ownerIds): string
    {
        /** @var class-string<Model>|null $owner */
        $owner = Relation::getMorphedModel($ownerType->value);

        if ($owner === null) {
            throw (new ModelNotFoundException)->setModel($ownerType->value, $ownerIds);
        }

        return $owner;
    }
}
