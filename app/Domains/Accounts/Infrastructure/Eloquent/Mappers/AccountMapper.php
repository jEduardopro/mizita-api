<?php

declare(strict_types=1);

namespace App\Domains\Accounts\Infrastructure\Eloquent\Mappers;

use App\Domains\Accounts\Entities\Account;
use App\Models\User;
use DateTimeImmutable;

/**
 * Translates between the persistence model and the domain entity. Only the
 * repository adapter uses it.
 *
 * The model is App\Models\User rather than a model this domain owns: users is
 * Fortify's and Sanctum's authenticatable and has to stay a single Eloquent
 * class. That is a deliberate divergence from one model per domain - the
 * entity is still pure, and the framework class stops here.
 */
final class AccountMapper
{
    public function toEntity(User $model): Account
    {
        return Account::restore(
            // The uuid is the domain identity; the int primary key stays here.
            id: $model->uuid,
            name: $model->name,
            email: $model->email,
            emailVerifiedAt: $model->email_verified_at === null
                ? null
                : DateTimeImmutable::createFromInterface($model->email_verified_at),
            createdAt: DateTimeImmutable::createFromInterface($model->created_at),
        );
    }

    /**
     * The password is deliberately absent. The entity carries no hash, so
     * omitting the attribute is what stops linking Google to an existing
     * password account from wiping that password.
     *
     * @return array<string, mixed>
     */
    public function toAttributes(Account $account): array
    {
        return [
            'uuid' => $account->id,
            'name' => $account->name(),
            'email' => $account->email(),
            'email_verified_at' => $account->emailVerifiedAt(),
        ];
    }
}
