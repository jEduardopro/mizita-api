<?php

declare(strict_types=1);

namespace App\Domains\Industries\Infrastructure\Eloquent\Mappers;

use App\Domains\Industries\Entities\Industry;
use App\Domains\Industries\Infrastructure\Eloquent\Models\IndustryModel;
use DateTimeImmutable;

final class IndustryMapper
{
    public function toEntity(IndustryModel $model): Industry
    {
        return Industry::restore(
            id: $model->uuid,
            key: $model->key,
            position: $model->position,
            active: $model->active,
            createdAt: DateTimeImmutable::createFromInterface($model->created_at),
        );
    }
}
