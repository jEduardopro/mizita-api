<?php

declare(strict_types=1);

namespace App\Domains\Addresses\Infrastructure\Eloquent;

use App\Domains\Addresses\Contracts\AddressRepository;
use App\Domains\Addresses\Entities\Address;
use App\Domains\Addresses\Exceptions\UnknownState;
use App\Domains\Addresses\Infrastructure\Eloquent\Mappers\AddressMapper;
use App\Domains\Addresses\Infrastructure\Eloquent\Models\AddressModel;
use App\Domains\Addresses\Infrastructure\Eloquent\Models\StateModel;
use App\Domains\Addresses\ValueObjects\AddressOwnerType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Eloquent\Relations\Relation;

final class EloquentAddressRepository implements AddressRepository
{
    private const STATE_SELECTION = 'state:id,uuid';

    public function __construct(
        private readonly AddressMapper $mapper,
    ) {}

    public function findForOwner(AddressOwnerType $ownerType, string $ownerId): ?Address
    {
        $model = $this->ownedBy($ownerType, $ownerId)->with(self::STATE_SELECTION)->first();

        if ($model === null) {
            return null;
        }

        return $this->mapper->toEntity($model, $ownerId);
    }

    public function save(Address $address): void
    {
        AddressModel::query()->updateOrCreate(
            ['uuid' => $address->id],
            $this->mapper->toAttributes(
                $address,
                $this->ownerKey($address->ownerType, $address->ownerId),
                $this->stateKey($address->stateId()),
            ),
        );
    }

    public function deleteForOwner(AddressOwnerType $ownerType, string $ownerId): void
    {
        $this->ownedBy($ownerType, $ownerId)->delete();
    }

    /**
     * @return Builder<AddressModel>
     */
    private function ownedBy(AddressOwnerType $ownerType, string $ownerId): Builder
    {
        return AddressModel::query()
            ->where('addressable_type', $ownerType->value)
            ->where('addressable_id', $this->ownerKey($ownerType, $ownerId));
    }

    private function ownerKey(AddressOwnerType $ownerType, string $ownerId): int
    {
        /** @var class-string<Model>|null $owner */
        $owner = Relation::getMorphedModel($ownerType->value);

        if ($owner === null) {
            throw (new ModelNotFoundException)->setModel($ownerType->value, [$ownerId]);
        }

        $key = $owner::query()->where('uuid', $ownerId)->value('id');

        if ($key === null) {
            throw (new ModelNotFoundException)->setModel($owner, [$ownerId]);
        }

        return (int) $key;
    }

    private function stateKey(?string $stateId): ?int
    {
        if ($stateId === null) {
            return null;
        }

        $key = StateModel::query()->where('uuid', $stateId)->value('id');

        if ($key === null) {
            throw UnknownState::withId($stateId);
        }

        return (int) $key;
    }
}
