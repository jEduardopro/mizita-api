<?php

declare(strict_types=1);

namespace App\Domains\Accounts\Infrastructure\Eloquent;

use App\Domains\Accounts\Contracts\AccountRepository;
use App\Domains\Accounts\Entities\Account;
use App\Domains\Accounts\Exceptions\AccountAlreadyRegistered;
use App\Domains\Accounts\Exceptions\AccountNotFound;
use App\Domains\Accounts\Infrastructure\Eloquent\Mappers\AccountMapper;
use App\Domains\Accounts\Infrastructure\Eloquent\Models\SocialIdentityModel;
use App\Domains\Accounts\ValueObjects\SocialProvider;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;

final class EloquentAccountRepository implements AccountRepository
{
    public function __construct(
        private readonly AccountMapper $mapper,
    ) {}

    public function findById(string $id): Account
    {
        $model = User::query()->where('uuid', $id)->first();

        if ($model === null) {
            throw AccountNotFound::withId($id);
        }

        return $this->toEntity($model);
    }

    public function findByEmail(string $email): ?Account
    {
        $model = User::query()
            ->whereRaw('lower(email) = ?', [mb_strtolower(trim($email))])
            ->first();

        return $model === null ? null : $this->toEntity($model);
    }

    /**
     * @param  list<string>  $ids
     * @return list<Account>
     */
    public function findManyByIds(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        $modelsById = User::query()
            ->whereIn('uuid', $ids)
            ->get()
            ->keyBy('uuid');

        $linkedProviders = $this->linkedSocialProvidersByAccountKey(
            $modelsById->map(static fn (User $model): int => (int) $model->getKey())->values()->all(),
        );

        $accounts = [];

        foreach ($ids as $id) {
            $model = $modelsById->get($id);

            if ($model instanceof User) {
                $accounts[] = $this->mapper->toEntity($model, $linkedProviders[(int) $model->getKey()] ?? []);
            }
        }

        return $accounts;
    }

    /**
     * @param  list<string>  $ids
     * @return list<string>
     */
    public function idsHoldingPassword(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        return User::query()
            ->whereIn('uuid', $ids)
            ->whereNotNull('password')
            ->pluck('uuid')
            ->map(static fn (mixed $uuid): string => (string) $uuid)
            ->values()
            ->all();
    }

    /**
     * @param  list<string>  $accountIds
     * @return list<string>
     */
    public function idsAwaitingPasswordChange(array $accountIds): array
    {
        if ($accountIds === []) {
            return [];
        }

        return User::query()
            ->whereIn('uuid', $accountIds)
            ->where('must_change_password', true)
            ->pluck('uuid')
            ->map(static fn (mixed $uuid): string => (string) $uuid)
            ->values()
            ->all();
    }

    public function save(Account $account): void
    {
        try {
            User::query()->updateOrCreate(
                ['uuid' => $account->id],
                $this->mapper->toAttributes($account),
            );
        } catch (UniqueConstraintViolationException $violation) {
            throw AccountAlreadyRegistered::withEmail($account->email(), $violation);
        }
    }

    private function toEntity(User $model): Account
    {
        $accountKey = (int) $model->getKey();

        return $this->mapper->toEntity(
            $model,
            $this->linkedSocialProvidersByAccountKey([$accountKey])[$accountKey] ?? [],
        );
    }

    /**
     * @param  list<int>  $accountKeys
     * @return array<int, list<SocialProvider>>
     */
    private function linkedSocialProvidersByAccountKey(array $accountKeys): array
    {
        if ($accountKeys === []) {
            return [];
        }

        $identities = SocialIdentityModel::query()
            ->whereIn('account_id', $accountKeys)
            ->get(['account_id', 'provider']);

        $linkedProviders = [];

        foreach ($identities as $identity) {
            $linkedProviders[(int) $identity->account_id][] = $identity->provider;
        }

        return $linkedProviders;
    }
}
