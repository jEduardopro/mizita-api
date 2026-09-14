<?php

declare(strict_types=1);

namespace App\Domains\Accounts\Infrastructure\Eloquent;

use App\Domains\Accounts\Contracts\SocialIdentityRepository;
use App\Domains\Accounts\Entities\SocialIdentity;
use App\Domains\Accounts\Exceptions\AccountNotFound;
use App\Domains\Accounts\Exceptions\SocialIdentityAlreadyLinked;
use App\Domains\Accounts\Infrastructure\Eloquent\Mappers\SocialIdentityMapper;
use App\Domains\Accounts\Infrastructure\Eloquent\Models\SocialIdentityModel;
use App\Domains\Accounts\ValueObjects\SocialProvider;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;

final class EloquentSocialIdentityRepository implements SocialIdentityRepository
{
    public function __construct(
        private readonly SocialIdentityMapper $mapper,
    ) {}

    public function findByProviderUserId(SocialProvider $provider, string $providerUserId): ?SocialIdentity
    {
        // The account is eager loaded because rehydrating the entity needs its
        // uuid: fetching it lazily would be a second query per row.
        $model = SocialIdentityModel::query()
            ->with('account')
            ->where('provider', $provider)
            ->where('provider_user_id', $providerUserId)
            ->first();

        if ($model === null) {
            return null;
        }

        $account = $model->account;

        // The foreign key makes this unreachable in practice; failing loudly
        // beats handing the domain an entity with no account behind it.
        if ($account === null) {
            throw AccountNotFound::withId((string) $model->account_id);
        }

        return $this->mapper->toEntity($model, $account->uuid);
    }

    public function save(SocialIdentity $identity): void
    {
        try {
            SocialIdentityModel::query()->updateOrCreate(
                ['uuid' => $identity->id],
                $this->mapper->toAttributes($identity, $this->accountKey($identity->accountId)),
            );
        } catch (UniqueConstraintViolationException $violation) {
            // The partial index on (provider, provider_user_id) rejected this.
            throw SocialIdentityAlreadyLinked::forProviderUser(
                $identity->provider,
                $identity->providerUserId,
                $violation,
            );
        }
    }

    private function accountKey(string $accountId): int
    {
        $key = User::query()->where('uuid', $accountId)->value('id');

        if ($key === null) {
            throw AccountNotFound::withId($accountId);
        }

        return (int) $key;
    }
}
