<?php

declare(strict_types=1);

namespace App\Domains\Links\Infrastructure\Eloquent\Mappers;

use App\Domains\Links\Entities\Link;
use App\Domains\Links\Infrastructure\Eloquent\Models\LinkModel;
use App\Domains\Links\ValueObjects\LinkOwnerType;
use App\Domains\Links\ValueObjects\LinkPlatform;
use App\Domains\Links\ValueObjects\LinkUrl;
use DateTimeImmutable;

final class LinkMapper
{
    public function toEntity(LinkModel $model, string $ownerId): Link
    {
        return Link::restore(
            id: $model->uuid,
            ownerType: LinkOwnerType::from($model->linkable_type),
            ownerId: $ownerId,
            platform: LinkPlatform::from($model->platform),
            url: LinkUrl::restore($model->url),
            position: $model->position,
            createdAt: DateTimeImmutable::createFromInterface($model->created_at),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toAttributes(Link $link, int $ownerKey): array
    {
        return [
            'uuid' => $link->id,
            'linkable_type' => $link->ownerType->value,
            'linkable_id' => $ownerKey,
            'platform' => $link->platform->value,
            'url' => $link->url()->value,
            'position' => $link->position(),
        ];
    }
}
