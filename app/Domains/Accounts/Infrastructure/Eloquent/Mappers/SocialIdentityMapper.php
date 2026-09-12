<?php

declare(strict_types=1);

namespace App\Domains\Accounts\Infrastructure\Eloquent\Mappers;

use App\Domains\Accounts\Entities\SocialIdentity;
use App\Domains\Accounts\Infrastructure\Eloquent\Models\SocialIdentityModel;
use DateTimeImmutable;

/**
 * Translates between the persistence model and the domain entity. Only the
 * repository adapter uses it.
 *
 * account_id is the users int primary key in the database and the account uuid
 * on the entity, so this mapper takes the uuid as a parameter on the way in
 * and the resolved int on the way out: neither number is invented here.
 */
final class SocialIdentityMapper
{
    public function toEntity(SocialIdentityModel $model, string $accountId): SocialIdentity
    {
        return SocialIdentity::restore(
            // The uuid is the domain identity; the int primary key stays here.
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
