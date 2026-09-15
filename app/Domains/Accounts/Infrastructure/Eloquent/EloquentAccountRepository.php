<?php

declare(strict_types=1);

namespace App\Domains\Accounts\Infrastructure\Eloquent;

use App\Domains\Accounts\Contracts\AccountRepository;
use App\Domains\Accounts\Entities\Account;
use App\Domains\Accounts\Exceptions\AccountAlreadyRegistered;
use App\Domains\Accounts\Exceptions\AccountNotFound;
use App\Domains\Accounts\Infrastructure\Eloquent\Mappers\AccountMapper;
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

        return $this->mapper->toEntity($model);
    }

    public function findByEmail(string $email): ?Account
    {
        $model = User::query()
            ->whereRaw('lower(email) = ?', [mb_strtolower(trim($email))])
            ->first();

        return $model === null ? null : $this->mapper->toEntity($model);
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

        $accounts = [];

        foreach ($ids as $id) {
            $model = $modelsById->get($id);

            if ($model instanceof User) {
                $accounts[] = $this->mapper->toEntity($model);
            }
        }

        return $accounts;
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
}
