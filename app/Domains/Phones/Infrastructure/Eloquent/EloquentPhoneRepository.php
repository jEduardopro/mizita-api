<?php

declare(strict_types=1);

namespace App\Domains\Phones\Infrastructure\Eloquent;

use App\Domains\Phones\Contracts\PhoneRepository;
use App\Domains\Phones\Entities\Phone;
use App\Domains\Phones\Infrastructure\Eloquent\Mappers\PhoneMapper;
use App\Domains\Phones\Infrastructure\Eloquent\Models\PhoneModel;
use App\Domains\Phones\ValueObjects\PhoneOwnerType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Eloquent\Relations\Relation;

final class EloquentPhoneRepository implements PhoneRepository
{
    public function __construct(
        private readonly PhoneMapper $mapper,
    ) {}

    public function findForOwner(PhoneOwnerType $ownerType, string $ownerId): ?Phone
    {
        $model = $this->ownedBy($ownerType, $ownerId)->first();

        if ($model === null) {
            return null;
        }

        // The owner uuid is handed back rather than looked up: the caller
        // supplied it, and reading it off the row would mean a second query for
        // something already known.
        return $this->mapper->toEntity($model, $ownerId);
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
     * The owning model comes from the morph map - every owner registers its
     * alias with enforceMorphMap in its own provider - so Phones resolves a
     * business, a staff member or a customer without importing any of them.
     *
     * The owner model's global scopes apply, so a CustomerModel is resolved
     * inside the current business. This works inside OnboardBusiness's
     * transaction because the business row is written before its phone is
     * attached, on the same connection.
     */
    private function ownerKey(PhoneOwnerType $ownerType, string $ownerId): int
    {
        /** @var class-string<Model>|null $owner */
        $owner = Relation::getMorphedModel($ownerType->value);

        if ($owner === null) {
            // The owning domain's provider never registered its alias, which
            // makes every phone it owns unreachable. A bug, not a failure a
            // caller should be told a sentence about.
            throw (new ModelNotFoundException)->setModel($ownerType->value, [$ownerId]);
        }

        $key = $owner::query()->where('uuid', $ownerId)->value('id');

        if ($key === null) {
            // Every caller attaches a phone to an owner it has just written or
            // just read, so a miss here is a bug and deserves a 500 rather than
            // a tidy error code.
            throw (new ModelNotFoundException)->setModel($owner, [$ownerId]);
        }

        return (int) $key;
    }
}
