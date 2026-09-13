<?php

declare(strict_types=1);

namespace App\Domains\Industries\Infrastructure\Eloquent\Mappers;

use App\Domains\Industries\Entities\Industry;
use App\Domains\Industries\Infrastructure\Eloquent\Models\IndustryModel;
use DateTimeImmutable;

/**
 * Translates the persistence model into the domain entity. Only the repository
 * adapter uses it.
 *
 * One direction only: the catalog is seeded, so nothing in this domain writes
 * an Industry back through a repository.
 */
final class IndustryMapper
{
    public function toEntity(IndustryModel $model): Industry
    {
        return Industry::restore(
            // The uuid is the domain identity; the int primary key stays here.
            id: $model->uuid,
            key: $model->key,
            position: $model->position,
            active: $model->active,
            createdAt: DateTimeImmutable::createFromInterface($model->created_at),
        );
    }
}
