<?php

declare(strict_types=1);

namespace App\Domains\Accounts\Infrastructure\Eloquent\Mappers;

use App\Domains\Accounts\Entities\SocialIdentity;
use App\Domains\Accounts\Infrastructure\Eloquent\Models\SocialIdentityModel;
use DateTimeImmutable;

final class SocialIdentityMapper
{
    public function toEntity(SocialIdentityModel $model, string $accountId): SocialIdentity
    {
        return SocialIdentity::restore(
            id: $model->uuid,
            accountId: $accountId,
            provider: $model->provider,
            providerUserId: $model->provider_user_id,
            linkedAt: DateTimeImmutable::createFromInterface($model->created_at),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toAttributes(SocialIdentity $identity, int $accountKey): array
    {
        return [
            'uuid' => $identity->id,
            'account_id' => $accountKey,
            'provider' => $identity->provider,
            'provider_user_id' => $identity->providerUserId,
        ];
    }
}
