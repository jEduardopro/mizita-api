<?php

declare(strict_types=1);

namespace App\Domains\Accounts\Infrastructure\Eloquent\Mappers;

use App\Domains\Accounts\Infrastructure\Eloquent\Models\PasskeyModel;
use App\Domains\Accounts\ValueObjects\RegisteredPasskey;
use DateTimeImmutable;

final class PasskeyMapper
{
    public function toRegisteredPasskey(PasskeyModel $model): RegisteredPasskey
    {
        return new RegisteredPasskey(
            id: $model->uuid,
            name: $model->name,
            authenticator: $model->authenticator,
            createdAt: DateTimeImmutable::createFromInterface($model->created_at),
            lastUsedAt: $model->last_used_at === null
                ? null
                : DateTimeImmutable::createFromInterface($model->last_used_at),
        );
    }
}
