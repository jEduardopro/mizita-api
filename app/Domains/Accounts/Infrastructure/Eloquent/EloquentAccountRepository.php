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

/**
 * Adapts App\Models\User rather than a model this domain owns: users is
 * Fortify's and Sanctum's authenticatable and has to stay a single Eloquent
 * class. The divergence from one model per domain stops at this boundary.
 */
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
        // Addresses are compared case insensitively: the same person typing
        // Ada@example.com must never end up with a second account.
        $model = User::query()
            ->whereRaw('lower(email) = ?', [mb_strtolower(trim($email))])
            ->first();

        return $model === null ? null : $this->mapper->toEntity($model);
    }

    /** The attribute list never includes the password, so an existing hash survives. */
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
