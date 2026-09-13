<?php

declare(strict_types=1);

namespace App\Domains\Phones\Infrastructure\Eloquent;

use App\Domains\Phones\Contracts\PhoneRepository;
use App\Domains\Phones\Entities\Phone;
use App\Domains\Phones\Infrastructure\Eloquent\Mappers\PhoneMapper;
use App\Domains\Phones\Infrastructure\Eloquent\Models\PhoneModel;
use App\Domains\Phones\ValueObjects\PhoneOwnerType;
use Illuminate\Database\Eloquent\Builder;

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

        return $this->mapper->toEntity($model);
    }

    public function save(Phone $phone): void
    {
        PhoneModel::query()->updateOrCreate(
            ['uuid' => $phone->id],
            $this->mapper->toAttributes($phone),
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
            ->where('phoneable_id', $ownerId);
    }
}
