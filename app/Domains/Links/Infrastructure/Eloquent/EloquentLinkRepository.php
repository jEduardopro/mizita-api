<?php

declare(strict_types=1);

namespace App\Domains\Links\Infrastructure\Eloquent;

use App\Domains\Links\Contracts\LinkRepository;
use App\Domains\Links\Entities\Link;
use App\Domains\Links\Infrastructure\Eloquent\Mappers\LinkMapper;
use App\Domains\Links\Infrastructure\Eloquent\Models\LinkModel;
use App\Domains\Links\ValueObjects\LinkOwnerType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Eloquent\Relations\Relation;

final class EloquentLinkRepository implements LinkRepository
{
    public function __construct(
        private readonly LinkMapper $mapper,
    ) {}

    /**
     * @return list<Link>
     */
    public function allForOwner(LinkOwnerType $ownerType, string $ownerId): array
    {
        return $this->ownedBy($ownerType, $ownerId)
            ->orderBy('position')
            ->orderBy('id')
            ->get()
            ->map(fn (LinkModel $model): Link => $this->mapper->toEntity($model, $ownerId))
            ->values()
            ->all();
    }

    public function save(Link $link): void
    {
        LinkModel::query()->updateOrCreate(
            ['uuid' => $link->id],
            $this->mapper->toAttributes($link, $this->ownerKey($link->ownerType, $link->ownerId)),
        );
    }

    /**
     * @param  list<Link>  $links
     */
    public function replaceForOwner(LinkOwnerType $ownerType, string $ownerId, array $links): void
    {
        $ownerKey = $this->ownerKey($ownerType, $ownerId);

        LinkModel::query()
            ->where('linkable_type', $ownerType->value)
            ->where('linkable_id', $ownerKey)
            ->delete();

        foreach ($links as $link) {
            LinkModel::query()->create($this->mapper->toAttributes($link, $ownerKey));
        }
    }

    public function deleteForOwner(LinkOwnerType $ownerType, string $ownerId): void
    {
        $this->ownedBy($ownerType, $ownerId)->delete();
    }

    /**
     * @return Builder<LinkModel>
     */
    private function ownedBy(LinkOwnerType $ownerType, string $ownerId): Builder
    {
        return LinkModel::query()
            ->where('linkable_type', $ownerType->value)
            ->where('linkable_id', $this->ownerKey($ownerType, $ownerId));
    }

    private function ownerKey(LinkOwnerType $ownerType, string $ownerId): int
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
}
