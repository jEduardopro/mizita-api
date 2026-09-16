<?php

declare(strict_types=1);

namespace App\Domains\Addresses\Infrastructure\Eloquent\Mappers;

use App\Domains\Addresses\Entities\State;
use App\Domains\Addresses\Infrastructure\Eloquent\Models\StateModel;
use App\Shared\ValueObjects\CountryCode;

final class StateMapper
{
    public function toEntity(StateModel $model): State
    {
        return State::restore(
            id: $model->uuid,
            country: CountryCode::from($model->country_code),
            code: $model->code,
            name: $model->name,
            position: $model->position,
            active: $model->active,
        );
    }
}
